<?php

namespace App\Http\Controllers\Settings;

use App\Enums\EmailStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Gmail\GmailImporter;
use App\Services\Google\GoogleCalendarService;
use App\Services\Google\GoogleTokenService;
use App\Services\Line\LineClient;
use App\Services\OpenAi\OpenAiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Connects the signed-in user's Google account for API access
 * (Calendar now, Gmail in a later phase).
 */
class GoogleConnectionController extends Controller
{
    public function edit(Request $request, OpenAiClient $openAi, LineClient $line): Response
    {
        $user = $request->user();

        return Inertia::render('settings/integrations', [
            'google' => [
                'configured' => filled(config('services.google.client_id')),
                'connected' => $user->hasGoogleCalendar(),
                'email' => $user->email,
                'calendarId' => $user->google_calendar_id,
                'scopes' => $user->google_scopes ?? [],
            ],
            'gmail' => [
                'connected' => $user->hasGmail(),
                'syncedAt' => $user->gmail_synced_at?->toAtomString(),
                'error' => $user->gmail_sync_error,
                'count' => $user->emails()->count(),
            ],
            'ai' => [
                'configured' => $openAi->configured(),
                'model' => $openAi->model(),
            ],
            'line' => [
                'configured' => $line->configured(),
                'userId' => $user->line_user_id,
                'linkedAt' => $user->line_linked_at?->toAtomString(),
                'webhookUrl' => route('line.webhook'),
                'morningAt' => config('services.line.morning_at'),
            ],
            'mailRules' => $user->mailRules()
                ->withCount(['emails as hidden' => fn ($q) => $q->where('status', EmailStatus::Excluded)])
                ->latest('id')
                ->get(['id', 'description', 'example_subject']),
        ]);
    }

    public function redirect(Request $request): SymfonyRedirect
    {
        return $this->provider()
            ->scopes(config('shukatsu.google_scopes'))
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
                'login_hint' => $request->user()->email,
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = $this->provider()->user();
        } catch (Throwable $e) {
            Log::warning('Google connect failed', ['error' => $e->getMessage()]);

            $this->toast('Google連携に失敗しました。もう一度お試しください。', 'error');

            return redirect()->route('integrations.edit');
        }

        $user = $request->user();

        if (strtolower((string) $googleUser->getEmail()) !== $user->email) {
            $this->toast("ログイン中のアカウント（{$user->email}）で連携してください。", 'error');

            return redirect()->route('integrations.edit');
        }

        $user->forceFill([
            'google_access_token' => $googleUser->token,
            // Google only returns a refresh token on the consent screen; keep
            // the old one if this response has none.
            'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
            'google_scopes' => $googleUser->approvedScopes,
        ])->save();

        $missing = collect([
            $user->hasGoogleCalendar() ? null : 'カレンダー',
            $user->hasGmail() ? null : 'Gmailの読み取り',
        ])->filter();

        $this->toast($missing->isEmpty()
            ? 'Googleと連携しました（カレンダー・Gmail）。'
            : $missing->implode('と').'へのアクセスが許可されませんでした。チェックを入れて再度連携してください。',
            $missing->isEmpty() ? 'success' : 'warning',
        );

        return redirect()->route('integrations.edit');
    }

    /**
     * Choose which calendar events are written to, e.g. a dedicated
     * "就活" calendar shared with another Google account.
     */
    public function updateCalendar(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $validated = $request->validate([
            'calendar_id' => ['required', 'string', 'max:255', 'regex:/^(primary|[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+)$/'],
        ], [
            'calendar_id.regex' => 'カレンダーIDの形式が正しくありません（例：xxxx@group.calendar.google.com）。',
        ]);

        $user = $request->user();
        $calendarId = trim($validated['calendar_id']);

        if ($calendarId !== $user->google_calendar_id) {
            $user->forceFill(['google_calendar_id' => $calendarId])->save();

            // Copies in the old calendar are left as they are. Upcoming
            // events are written to the new calendar right away.
            $user->events()->whereNotNull('google_event_id')->update([
                'google_event_id' => null,
                'google_synced_at' => null,
            ]);

            $failed = 0;

            if ($user->hasGoogleCalendar()) {
                $user->events()->upcoming()->where('sync_google', true)->with('company', 'user')
                    ->each(function (Event $event) use ($calendar, &$failed) {
                        $failed += $calendar->sync($event) ? 0 : 1;
                    });
            }

            if ($failed > 0) {
                $this->toast("書き込み先を変更しましたが、{$failed}件の予定を反映できませんでした。カレンダーIDと共有設定を確認してください。", 'warning');

                return redirect()->route('integrations.edit');
            }
        }

        $this->toast('書き込み先のカレンダーを変更しました。');

        return redirect()->route('integrations.edit');
    }

    /**
     * Import new job-hunting mails now instead of waiting for the scheduler.
     */
    public function syncGmail(Request $request, GmailImporter $importer): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasGmail()) {
            $this->toast('Gmailの読み取りが許可されていません。再連携してください。', 'warning');

            return back();
        }

        $stored = $importer->tryImport($user);

        $stored === null
            ? $this->toast((string) $user->gmail_sync_error, 'error')
            : $this->toast("メールを{$stored}件取り込みました。AIの判定は順番に進みます。");

        return back();
    }

    public function destroy(Request $request, GoogleTokenService $tokens): RedirectResponse
    {
        $tokens->disconnect($request->user());

        $this->toast('Google連携を解除しました。');

        return redirect()->route('integrations.edit');
    }

    private function provider(): GoogleProvider
    {
        /** @var GoogleProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->redirectUrl(url(config('services.google.connect_redirect')));
    }
}
