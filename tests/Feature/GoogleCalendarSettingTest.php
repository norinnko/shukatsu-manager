<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarSettingTest extends TestCase
{
    use RefreshDatabase;

    private const CALENDAR = 'abc123@group.calendar.google.com';

    public function test_changing_the_calendar_moves_upcoming_events(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['id' => 'new-event'])]);
        $user = User::factory()->withGoogleCalendar()->create();
        $upcoming = Event::factory()->for($user)->create(['sync_google' => true, 'google_event_id' => 'old-1']);
        $done = Event::factory()->for($user)->create([
            'sync_google' => true,
            'status' => EventStatus::Done,
            'google_event_id' => 'old-2',
        ]);

        $this->actingAs($user)
            ->put(route('google.calendar.update'), ['calendar_id' => self::CALENDAR])
            ->assertRedirect(route('integrations.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::CALENDAR, $user->fresh()->google_calendar_id);
        $this->assertSame('new-event', $upcoming->fresh()->google_event_id);
        $this->assertNull($done->fresh()->google_event_id);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/calendars/'.rawurlencode(self::CALENDAR).'/events'));
        Http::assertSentCount(1);
    }

    public function test_invalid_calendar_id_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('google.calendar.update'), ['calendar_id' => 'not a calendar'])
            ->assertSessionHasErrors('calendar_id');

        $this->assertSame('primary', $user->fresh()->google_calendar_id);
    }
}
