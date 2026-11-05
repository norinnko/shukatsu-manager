<?php

namespace App\Services\Gmail;

use App\Enums\EmailStatus;
use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Email;
use App\Models\Event;
use App\Services\Google\GoogleCalendarService;
use Carbon\CarbonImmutable;

/**
 * Applies a calendar action OpenAI found in a mail (add / move / cancel an
 * event). Only ever called after the user clicked a button, in the inbox or
 * on LINE.
 */
class MailActionService
{
    public function __construct(private GoogleCalendarService $calendar)
    {
        //
    }

    /**
     * The not-yet-applied action at $index, if it is one of $kinds.
     *
     * @param  list<string>  $kinds
     * @return array<string, mixed>
     *
     * @throws MailActionException
     */
    public function pending(Email $email, int $index, array $kinds): array
    {
        $action = $email->ai_actions[$index] ?? null;

        if (! is_array($action) || ! in_array($action['kind'] ?? null, $kinds, true)) {
            throw new MailActionException('この操作は見つかりませんでした。', 404);
        }

        if ($action['applied'] ?? false) {
            throw new MailActionException('この予定はすでに反映済みです。', 409);
        }

        return $action;
    }

    /**
     * Add an event as the user confirmed (and possibly edited) it.
     *
     * @param  array<string, mixed>  $data  event attributes (EventRequest::eventData())
     * @return array{0: Event, 1: string, 2: 'success'|'warning'} event, message, toast type
     */
    public function create(Email $email, int $index, array $data): array
    {
        $this->pending($email, $index, [MailAssessor::ACTION_CREATE]);

        $event = $email->user->events()->create([...$data, 'source' => EventSource::Gmail]);
        $this->markApplied($email, $index);

        return [$event, ...$this->sync($event, '予定を追加しました')];
    }

    /**
     * Add the event exactly as OpenAI read it (the LINE "✅追加" button).
     *
     * @return array{0: Event, 1: string, 2: 'success'|'warning'}
     */
    public function createAsSuggested(Email $email, int $index): array
    {
        $action = $this->pending($email, $index, [MailAssessor::ACTION_CREATE]);
        $allDay = $action['start_time'] === null;

        return $this->create($email, $index, [
            'company_id' => $email->company_id,
            'type' => EventType::tryFrom((string) $action['type']) ?? EventType::Other,
            'title' => $action['title'],
            'all_day' => $allDay,
            'starts_at' => CarbonImmutable::parse(trim("{$action['date']} {$action['start_time']}")),
            'ends_at' => $this->end($action, $allDay),
            'location' => $action['location'],
            'online_url' => $action['online_url'],
            'memo' => $email->subject,
            'sync_google' => $email->user->hasGoogleCalendar(),
        ]);
    }

    /**
     * Move or cancel the event the mail is about.
     *
     * @return array{0: Event, 1: string, 2: 'success'|'warning'}
     */
    public function apply(Email $email, int $index): array
    {
        $action = $this->pending($email, $index, [MailAssessor::ACTION_UPDATE, MailAssessor::ACTION_CANCEL]);
        $event = is_int($action['event_id']) ? $email->user->events()->find($action['event_id']) : null;

        if ($event === null) {
            throw new MailActionException('対象の予定が見つかりませんでした。', 404);
        }

        if ($action['kind'] === MailAssessor::ACTION_CANCEL) {
            $event->update(['status' => EventStatus::Cancelled]);
            $this->calendar->delete($event);
            $this->markApplied($email, $index);

            return [$event, "「{$event->displayTitle()}」をキャンセルにしました。", 'success'];
        }

        $event->update($this->movedTimes($event, $action) + array_filter([
            'location' => $action['location'],
            'online_url' => $action['online_url'],
        ]));
        $this->markApplied($email, $index);

        return [$event, ...$this->sync($event, '予定の日時を変更しました')];
    }

    /**
     * Whether the event a "create" action suggests is already on the
     * calendar, e.g. added from an earlier reminder of the same deadline.
     *
     * @param  array<string, mixed>  $action
     * @param  iterable<Event>  $events  the user's events on that day
     */
    public static function isRegistered(array $action, iterable $events): bool
    {
        if (($action['kind'] ?? null) !== MailAssessor::ACTION_CREATE) {
            return false;
        }

        foreach ($events as $event) {
            if ($event->status !== EventStatus::Cancelled
                && $event->type->value === $action['type']
                && $event->starts_at->toDateString() === $action['date']
                && ($action['start_time'] === null || $event->all_day || $event->starts_at->format('H:i') === $action['start_time'])) {
                return true;
            }
        }

        return false;
    }

    public function ignore(Email $email): void
    {
        $email->update(['status' => EmailStatus::Ignored]);
    }

    private function markApplied(Email $email, int $index): void
    {
        $actions = $email->ai_actions ?? [];
        $actions[$index]['applied'] = true;

        $email->forceFill(['ai_actions' => $actions, 'status' => EmailStatus::Done])->save();
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function end(array $action, bool $allDay): ?CarbonImmutable
    {
        if ($allDay) {
            return $action['end_date'] !== null && $action['end_date'] !== $action['date']
                ? CarbonImmutable::parse($action['end_date'])
                : null;
        }

        return $action['end_time'] !== null
            ? CarbonImmutable::parse(($action['end_date'] ?? $action['date'])." {$action['end_time']}")
            : null;
    }

    /**
     * New start / end for a moved event. Without a new start time the
     * event keeps its time of day (or stays all-day).
     *
     * @param  array<string, mixed>  $action
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, all_day: bool}
     */
    private function movedTimes(Event $event, array $action): array
    {
        $duration = $event->ends_at?->diffInMinutes($event->starts_at, true);
        $startTime = $action['start_time'] ?? ($event->all_day ? null : $event->starts_at->format('H:i'));
        $start = CarbonImmutable::parse(trim("{$action['date']} {$startTime}"));

        $end = match (true) {
            $action['end_time'] !== null => CarbonImmutable::parse(($action['end_date'] ?? $action['date'])." {$action['end_time']}"),
            $action['end_date'] !== null => CarbonImmutable::parse($action['end_date']),
            $duration !== null => $start->addMinutes((int) $duration),
            default => null,
        };

        return ['starts_at' => $start, 'ends_at' => $end, 'all_day' => $startTime === null];
    }

    /**
     * @return array{0: string, 1: 'success'|'warning'} message, toast type
     */
    private function sync(Event $event, string $message): array
    {
        $event->loadMissing('company', 'user');

        if (! $event->sync_google || ! $event->user->hasGoogleCalendar()) {
            return ["{$message}。", 'success'];
        }

        return $this->calendar->sync($event)
            ? ["{$message}（Googleカレンダーにも反映）。", 'success']
            : ["{$message}。ただしGoogleカレンダーへの反映に失敗しました。", 'warning'];
    }
}
