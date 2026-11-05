<?php

namespace App\Services\Google;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes app events to Google Calendar. The app is the source of truth:
 * changes are written one way, from here to Google.
 */
class GoogleCalendarService
{
    private const API = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private GoogleTokenService $tokens)
    {
        //
    }

    /**
     * Create or update the Google event for an app event. Failures are
     * recorded on the event instead of thrown, so saving in the app never
     * fails because Google is unreachable.
     */
    public function sync(Event $event): bool
    {
        $user = $event->user;

        if (! $event->sync_google || ! $user->hasGoogleCalendar()) {
            return false;
        }

        try {
            $calendar = rawurlencode($user->google_calendar_id);
            $payload = $this->payload($event);

            $response = $event->google_event_id
                ? $this->client($user)->put(self::API."/calendars/{$calendar}/events/".rawurlencode($event->google_event_id), $payload)
                : $this->client($user)->post(self::API."/calendars/{$calendar}/events", $payload);

            // The Google event was deleted on the Google side: create it again.
            if ($event->google_event_id && in_array($response->status(), [404, 410], true)) {
                $response = $this->client($user)->post(self::API."/calendars/{$calendar}/events", $payload);
            }

            $response->throw();

            $event->forceFill([
                'google_event_id' => $response->json('id'),
                'google_synced_at' => now(),
                'google_sync_error' => null,
            ])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            Log::warning('Google Calendar sync failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            $event->forceFill(['google_sync_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();

            return false;
        }
    }

    /**
     * Remove the Google copy of an event (e.g. when deleted in the app or
     * when syncing is turned off).
     */
    public function delete(Event $event): void
    {
        $user = $event->user;

        if ($event->google_event_id === null || ! $user->hasGoogleCalendar()) {
            return;
        }

        try {
            $calendar = rawurlencode($user->google_calendar_id);
            $response = $this->client($user)->delete(self::API."/calendars/{$calendar}/events/".rawurlencode($event->google_event_id));

            if (! in_array($response->status(), [404, 410], true)) {
                $response->throw();
            }
        } catch (Throwable $e) {
            Log::warning('Google Calendar delete failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }

        $event->forceFill(['google_event_id' => null, 'google_synced_at' => null])->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Event $event): array
    {
        $timezone = config('app.timezone');

        if ($event->all_day) {
            // Google's all-day end date is exclusive.
            $start = ['date' => $event->starts_at->toDateString()];
            $end = ['date' => ($event->ends_at ?? $event->starts_at)->addDay()->toDateString()];
        } else {
            $start = ['dateTime' => $event->starts_at->toIso8601String(), 'timeZone' => $timezone];
            $end = ['dateTime' => ($event->ends_at ?? $event->starts_at->addHour())->toIso8601String(), 'timeZone' => $timezone];
        }

        $description = collect([
            $event->type->label(),
            $event->online_url ? "参加URL: {$event->online_url}" : null,
            $event->company?->mypage_url ? "マイページ: {$event->company->mypage_url}" : null,
            $event->memo,
            '— 就活マネージャーから登録: '.route('calendar.index', ['month' => $event->starts_at->format('Y-m')]),
        ])->filter()->implode("\n\n");

        return [
            'summary' => $event->displayTitle(),
            'description' => $description,
            'location' => $event->location ?? $event->online_url,
            'start' => $start,
            'end' => $end,
            'colorId' => $event->type->googleColorId(),
            'extendedProperties' => [
                'private' => ['shukatsu_event_id' => (string) $event->id],
            ],
        ];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($user))
            ->acceptJson()
            ->timeout(15);
    }
}
