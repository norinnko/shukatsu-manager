<?php

namespace App\Services\Line;

use App\Enums\EmailStatus;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Models\Email;
use App\Models\Event;
use App\Models\User;
use App\Services\Gmail\MailActionService;
use App\Services\Gmail\MailAssessor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the app sends to LINE: one summary every morning, and a card with
 * buttons when an important mail arrives. The free plan allows 200 pushes
 * a month, so mail cards are bundled into one carousel per run and a
 * reserve is kept for the morning summary.
 */
class LineNotifier
{
    /** Bubbles in one carousel (LINE allows up to 12). */
    private const MAX_BUBBLES = 10;

    /** Reminders this many days before an event. */
    private const REMIND_DAYS = [7, 3, 1];

    public function __construct(private LineClient $line)
    {
        //
    }

    /**
     * @return bool Whether a summary was sent (nothing is sent on an empty day).
     */
    public function sendMorning(User $user, ?CarbonImmutable $today = null): bool
    {
        $text = $this->morningText($user, $today ?? CarbonImmutable::now());

        if ($text === null || $user->line_user_id === null || $this->line->remainingPushes() === 0) {
            return false;
        }

        $this->line->push($user->line_user_id, [['type' => 'text', 'text' => $text]]);

        return true;
    }

    public function morningText(User $user, CarbonImmutable $today): ?string
    {
        $events = $user->events()->upcoming()->with('company:id,name')
            ->whereBetween('starts_at', [$today->startOfDay(), $today->addDays(max(self::REMIND_DAYS))->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        $todays = $events->filter(fn (Event $e) => $e->starts_at->isSameDay($today));
        $reminders = $events->filter(fn (Event $e) => in_array((int) $today->startOfDay()->diffInDays($e->starts_at->startOfDay()), self::REMIND_DAYS, true));
        $unread = $user->emails()->where('status', EmailStatus::New)->count();

        if ($todays->isEmpty() && $reminders->isEmpty() && $unread === 0) {
            return null;
        }

        $lines = ['おはようございます（'.$today->isoFormat('M/D ddd').'）'];

        if ($todays->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '■ 今日の予定';
            foreach ($todays as $event) {
                $lines[] = '・'.$this->time($event).' '.$event->displayTitle();
                if ($event->online_url) {
                    $lines[] = '  '.$event->online_url;
                } elseif ($event->location) {
                    $lines[] = '  '.$event->location;
                }
            }
        }

        if ($reminders->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '■ 近づいている予定';
            foreach ($reminders as $event) {
                $days = (int) $today->startOfDay()->diffInDays($event->starts_at->startOfDay());
                $when = $days === 1 ? '明日' : "あと{$days}日";
                $lines[] = "・{$event->starts_at->isoFormat('M/D（ddd）')} {$when} {$event->displayTitle()}";
            }
        }

        if ($unread > 0) {
            $lines[] = '';
            $lines[] = "■ 受信箱：未確認 {$unread}件";
            $lines[] = route('inbox.index');
        }

        return implode("\n", $lines);
    }

    /**
     * Push the important mails that arrived since the last run as one
     * carousel.
     *
     * @return int Number of mails sent.
     */
    public function sendNewMails(User $user): int
    {
        if ($user->line_user_id === null || $user->line_linked_at === null) {
            return 0;
        }

        $emails = $this->importantMails($user);

        if ($emails->isEmpty()) {
            return 0;
        }

        $remaining = $this->line->remainingPushes();

        // Keep the reserve for the morning summaries.
        if ($remaining !== null && $remaining <= (int) config('services.line.reserve')) {
            return 0;
        }

        $bubbles = $emails->map(fn (Email $email) => $this->bubble($email))->all();

        $this->line->push($user->line_user_id, [[
            'type' => 'flex',
            'altText' => '就活メール '.count($bubbles).'件：'.$emails->first()->subject,
            'contents' => ['type' => 'carousel', 'contents' => $bubbles],
        ]]);

        Email::whereIn('id', $emails->map(fn (Email $e) => $e->id)->all())->update(['line_notified_at' => now()]);

        return $emails->count();
    }

    /**
     * Unread, judged mails received after LINE was linked that either offer
     * a calendar action or report a selection result.
     *
     * @return Collection<int, Email>
     */
    public function importantMails(User $user): Collection
    {
        $emails = $user->emails()
            ->with('company:id,name')
            ->where('status', EmailStatus::New)
            ->where('ai_status', MailAiStatus::Done)
            ->whereNull('line_notified_at')
            ->where('received_at', '>=', $user->line_linked_at)
            ->oldest('received_at')
            ->get();

        $days = $emails->flatMap(fn (Email $e) => array_column($e->ai_actions ?? [], 'date'))->filter()->unique();
        $events = $days->isEmpty() ? collect() : $user->events()
            ->where(fn ($q) => $days->each(fn ($d) => $q->orWhereBetween('starts_at', [$d.' 00:00:00', $d.' 23:59:59'])))
            ->get();

        return $emails
            ->filter(fn (Email $e) => $e->ai_category === MailCategory::Result || $this->openActions($e, $events) !== [])
            ->take(self::MAX_BUBBLES)
            ->values();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, mixed>> index => action
     */
    private function openActions(Email $email, Collection $events): array
    {
        return array_filter(
            $email->ai_actions ?? [],
            fn (array $a) => ! ($a['applied'] ?? false) && ! MailActionService::isRegistered($a, $events),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bubble(Email $email): array
    {
        $buttons = [];

        foreach ($email->ai_actions ?? [] as $index => $action) {
            if (($action['applied'] ?? false) || count($buttons) >= 2) {
                continue;
            }

            $op = $action['kind'] === MailAssessor::ACTION_CREATE ? 'add' : 'apply';
            $label = match ($action['kind']) {
                MailAssessor::ACTION_CREATE => '✅ 追加 '.$this->actionWhen($action),
                MailAssessor::ACTION_UPDATE => '🔁 変更 '.$this->actionWhen($action),
                default => '🗑 キャンセルにする',
            };

            $buttons[] = $this->button(['type' => 'postback', 'label' => mb_substr($label, 0, 20),
                'data' => http_build_query(['op' => $op, 'email' => $email->id, 'index' => $index]),
                'displayText' => $label], 'primary');
        }

        $buttons[] = $this->button(['type' => 'uri', 'label' => '✏️ 修正・詳細', 'uri' => route('inbox.index')], 'secondary');
        $buttons[] = $this->button(['type' => 'postback', 'label' => '❌ 無視',
            'data' => http_build_query(['op' => 'ignore', 'email' => $email->id]), 'displayText' => '無視'], 'secondary');

        $heading = collect([$email->ai_category?->label(), $email->company?->name])->filter()->implode('・');

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => array_values(array_filter([
                    $heading !== '' ? ['type' => 'text', 'text' => $heading, 'size' => 'xs', 'color' => '#888888'] : null,
                    ['type' => 'text', 'text' => mb_substr($email->subject, 0, 120), 'weight' => 'bold', 'size' => 'sm', 'wrap' => true, 'maxLines' => 3],
                    $email->ai_summary ? ['type' => 'text', 'text' => mb_substr($email->ai_summary, 0, 300), 'size' => 'xs', 'wrap' => true, 'color' => '#555555'] : null,
                ])),
            ],
            'footer' => ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'contents' => $buttons],
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function button(array $action, string $style): array
    {
        return ['type' => 'button', 'style' => $style, 'height' => 'sm', 'action' => $action];
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function actionWhen(array $action): string
    {
        $date = CarbonImmutable::parse((string) $action['date'])->isoFormat('M/D');

        return $action['start_time'] ? "{$date} {$action['start_time']}" : $date;
    }

    private function time(Event $event): string
    {
        if ($event->all_day) {
            return '終日';
        }

        return $event->starts_at->format('H:i').($event->ends_at ? '〜'.$event->ends_at->format('H:i') : '');
    }
}
