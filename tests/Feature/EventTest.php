<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use App\Services\Google\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_timed_event_is_created_from_date_and_time_fields(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user)->create();

        $this->actingAs($user)->post(route('events.store'), [
            'company_id' => $company->id,
            'type' => 'interview',
            'title' => '一次面接',
            'all_day' => false,
            'start_date' => '2026-10-02',
            'start_time' => '14:00',
            'end_time' => '15:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $event = $user->events()->sole();
        $this->assertSame('2026-10-02 14:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-02 15:00', $event->ends_at->format('Y-m-d H:i'));
    }

    public function test_all_day_multi_day_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'type' => 'intern',
            'title' => '5daysインターン',
            'all_day' => true,
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
        ])->assertSessionHasNoErrors();

        $event = $user->events()->sole();
        $this->assertTrue($event->all_day);
        $this->assertSame('2027-02-05', $event->ends_at->toDateString());
        $this->assertSame('2027-02-05 23:59:59', $event->dueAt()->format('Y-m-d H:i:s'));
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('events.store'), [
            'type' => 'interview',
            'title' => 'x',
            'all_day' => false,
            'start_date' => '2026-10-02',
            'start_time' => '14:00',
            'end_time' => '13:00',
        ])->assertSessionHasErrors('end_time');
    }

    public function test_event_cannot_be_attached_to_another_users_company(): void
    {
        $foreign = Company::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('events.store'), [
            'company_id' => $foreign->id,
            'type' => 'deadline',
            'title' => 'x',
            'all_day' => true,
            'start_date' => '2026-10-02',
        ])->assertSessionHasErrors('company_id');
    }

    public function test_status_can_be_changed(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($event->user)
            ->patch(route('events.status', $event), ['status' => 'done'])
            ->assertRedirect();

        $this->assertSame(EventStatus::Done, $event->fresh()->status);
    }

    public function test_other_users_events_are_not_found(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('events.destroy', $event))
            ->assertNotFound();

        $this->assertModelExists($event);
    }

    public function test_event_is_pushed_to_google_calendar_when_connected(): void
    {
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response(['id' => 'g-event-1']),
        ]);
        $user = User::factory()->withGoogleCalendar()->create();
        $company = Company::factory()->for($user)->create(['name' => 'SHIFT']);

        $this->actingAs($user)->post(route('events.store'), [
            'company_id' => $company->id,
            'type' => 'interview',
            'title' => '一次面接',
            'all_day' => false,
            'start_date' => '2026-10-02',
            'start_time' => '14:00',
            'sync_google' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('g-event-1', $user->events()->sole()->google_event_id);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['summary'] === '【SHIFT】一次面接'
            && $request['start']['timeZone'] === 'Asia/Tokyo'
            && $request['colorId'] === '10'
            && $request->hasHeader('Authorization', 'Bearer test-access-token'));
    }

    public function test_all_day_google_event_uses_exclusive_end_date(): void
    {
        $event = Event::factory()->create([
            'all_day' => true,
            'starts_at' => '2027-02-01 00:00:00',
            'ends_at' => '2027-02-05 00:00:00',
        ]);

        $payload = app(GoogleCalendarService::class)->payload($event);

        $this->assertSame(['date' => '2027-02-01'], $payload['start']);
        $this->assertSame(['date' => '2027-02-06'], $payload['end']);
    }

    public function test_google_failure_does_not_break_saving(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]);
        $user = User::factory()->withGoogleCalendar()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'type' => 'deadline',
            'title' => 'ES締切',
            'all_day' => true,
            'start_date' => '2026-10-02',
            'sync_google' => true,
        ])->assertRedirect();

        $event = $user->events()->sole();
        $this->assertNull($event->google_event_id);
        $this->assertNotNull($event->google_sync_error);
    }

    public function test_expired_access_token_is_refreshed(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'www.googleapis.com/*' => Http::response(['id' => 'g-1']),
        ]);
        $user = User::factory()->withGoogleCalendar()->create(['google_token_expires_at' => now()->subMinute()]);

        $this->actingAs($user)->post(route('events.store'), [
            'type' => 'deadline',
            'title' => 'ES締切',
            'all_day' => true,
            'start_date' => '2026-10-02',
            'sync_google' => true,
        ]);

        $this->assertSame('fresh-token', $user->fresh()->google_access_token);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'calendar/v3')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }
}
