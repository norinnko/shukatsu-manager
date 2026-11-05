<?php

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Enums\MailAiStatus;
use App\Enums\MailDecision;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EmailResource;
use App\Models\Email;
use App\Services\Gmail\MailActionException;
use App\Services\Gmail\MailActionService;
use App\Services\Gmail\MailAssessor;
use App\Services\Gmail\MailRuleLearner;
use App\Services\OpenAi\OpenAiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Mails imported from Gmail, judged by OpenAI, waiting to be checked.
 */
class InboxController extends Controller
{
    /** Mails judged per click on "AI判定を今すぐ実行". */
    private const ASSESS_NOW_LIMIT = 5;

    public function index(Request $request, OpenAiClient $openAi): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(EmailStatus::class)],
        ]);

        $user = $request->user();
        $status = $filters['status'] ?? EmailStatus::New->value;

        $emails = $user->emails()
            ->with(['company:id,name', 'mailRule:id,description'])
            ->where('status', $status)
            // "念のため表示" first, so they are not overlooked.
            ->orderByRaw('ai_decision = ? desc', [MailDecision::Unsure->value])
            ->latest('received_at')
            ->limit(200)
            ->get();

        return Inertia::render('inbox/index', [
            'emails' => EmailResource::collection($emails)->resolve(),
            'counts' => $user->emails()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'status' => $status,
            'gmail' => [
                'connected' => $user->hasGmail(),
                'syncedAt' => $user->gmail_synced_at?->toAtomString(),
                'error' => $user->gmail_sync_error,
            ],
            'ai' => [
                'configured' => $openAi->configured(),
                'pending' => $user->emails()->where('ai_status', MailAiStatus::Pending)->count(),
                'failed' => $user->emails()->where('ai_status', MailAiStatus::Failed)->count(),
            ],
            'companies' => $user->companies()->active()->orderBy('name')->get(['id', 'name']),
            'googleConnected' => $user->hasGoogleCalendar(),
        ]);
    }

    /**
     * 確認済み / 無視 / 戻す. "無視" affects this mail only.
     */
    public function updateStatus(Request $request, Email $email): RedirectResponse
    {
        Gate::authorize('own', $email);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(EmailStatus::class)],
        ]);

        $email->update($validated);

        return back();
    }

    /**
     * 次から表示しない: learn a rule from this mail and hide similar ones.
     */
    public function hideSimilar(Email $email, MailRuleLearner $learner): RedirectResponse
    {
        Gate::authorize('own', $email);

        try {
            $rule = $learner->learn($email);
        } catch (Throwable $e) {
            Log::warning('Mail rule learning failed', ['email_id' => $email->id, 'error' => $e->getMessage()]);
            $this->toast('ルールを作れませんでした: '.$e->getMessage(), 'error');

            return back();
        }

        $this->toast("次から表示しないルールを追加しました：{$rule->description}");

        return back();
    }

    /**
     * Judge a few waiting mails now instead of waiting for the scheduler.
     */
    public function assessNow(Request $request, MailAssessor $assessor): RedirectResponse
    {
        $user = $request->user();

        // Retry the ones that failed before as well.
        $user->emails()->where('ai_status', MailAiStatus::Failed)->update(['ai_status' => MailAiStatus::Pending]);

        $judged = $assessor->assessPending($user, self::ASSESS_NOW_LIMIT);
        $failed = $user->emails()->where('ai_status', MailAiStatus::Failed)->count();

        $failed > 0
            ? $this->toast("{$judged}件を判定しましたが、{$failed}件は失敗しました。", 'warning')
            : $this->toast("{$judged}件をAIで判定しました。");

        return back();
    }

    /**
     * Add an event the user confirmed (and possibly edited) from a mail.
     */
    public function storeEvent(EventRequest $request, Email $email, int $index, MailActionService $actions): RedirectResponse
    {
        Gate::authorize('own', $email);

        return $this->run(fn () => $actions->create($email, $index, $request->eventData()));
    }

    /**
     * Move or cancel an existing event as the mail says, after one click.
     */
    public function applyAction(Email $email, int $index, MailActionService $actions): RedirectResponse
    {
        Gate::authorize('own', $email);

        return $this->run(fn () => $actions->apply($email, $index));
    }

    /**
     * @param  callable(): array{0: mixed, 1: string, 2: 'success'|'warning'}  $apply
     */
    private function run(callable $apply): RedirectResponse
    {
        try {
            [, $message, $type] = $apply();
        } catch (MailActionException $e) {
            abort($e->getCode(), $e->getMessage());
        }

        $this->toast($message, $type);

        return back();
    }
}
