<?php

namespace Tests\Feature;

use App\Enums\EmailStatus;
use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Enums\MailDecision;
use App\Models\Email;
use App\Models\Event;
use App\Models\MailRule;
use App\Models\User;
use App\Services\Gmail\MailAssessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailTriageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'gpt-5-mini']);
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function fakeOpenAi(array $answer): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($answer), 'refusal' => null]]],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function answer(array $overrides = []): array
    {
        return [
            'category' => 'ad',
            'summary' => '就活サイトの宣伝メール',
            'decision' => 'keep',
            'rule_id' => null,
            'reason' => '',
            'company_name' => null,
            'signup_url' => null,
            'actions' => [],
            ...$overrides,
        ];
    }

    private function mail(User $user, string $subject = '【初任給710万円】早期選考エントリー'): Email
    {
        return $user->emails()->create([
            'gmail_message_id' => uniqid('m'),
            'from_address' => 'info@saiyo.jp',
            'subject' => $subject,
            'body' => '本文',
            'received_at' => now(),
        ]);
    }

    public function test_mail_matching_a_rule_is_excluded(): void
    {
        $user = User::factory()->withGmail()->create();
        $rule = $user->mailRules()->create(['description' => '初任給の高さを売りにした早期選考の宣伝メール']);
        $email = $this->mail($user);
        $this->fakeOpenAi($this->answer(['decision' => 'exclude', 'rule_id' => $rule->id, 'reason' => '同じ宣伝']));

        app(MailAssessor::class)->assessPending($user, 10);

        $email->refresh();
        $this->assertSame(EmailStatus::Excluded, $email->status);
        $this->assertSame(MailDecision::Exclude, $email->ai_decision);
        $this->assertSame($rule->id, $email->mail_rule_id);
        $this->assertSame(MailAiStatus::Done, $email->ai_status);

        Http::assertSent(fn (Request $r) => $r['model'] === 'gpt-5-mini'
            && str_contains($r['messages'][1]['content'], "[ID {$rule->id}] 初任給の高さを売りにした"));
    }

    public function test_interviews_results_and_deadlines_are_never_excluded(): void
    {
        $user = User::factory()->withGmail()->create();
        $rule = $user->mailRules()->create(['description' => 'axol から届く説明会の案内']);
        $email = $this->mail($user, '一次面接の日程のご案内');
        $this->fakeOpenAi($this->answer(['category' => 'schedule', 'decision' => 'exclude', 'rule_id' => $rule->id]));

        app(MailAssessor::class)->assessPending($user, 10);

        $email->refresh();
        $this->assertSame(EmailStatus::New, $email->status);
        $this->assertSame(MailDecision::Unsure, $email->ai_decision);
        $this->assertStringContainsString('選考の予定・結果・締切', $email->ai_reason);
    }

    public function test_nothing_is_excluded_without_one_of_the_users_rules(): void
    {
        $user = User::factory()->withGmail()->create();
        $email = $this->mail($user);
        $this->fakeOpenAi($this->answer(['decision' => 'exclude', 'rule_id' => 999]));

        app(MailAssessor::class)->assessPending($user, 10);

        $this->assertSame(EmailStatus::New, $email->fresh()->status);
        $this->assertSame(MailDecision::Keep, $email->fresh()->ai_decision);
    }

    public function test_unsure_mails_stay_in_the_inbox_with_a_reason(): void
    {
        $user = User::factory()->withGmail()->create();
        $rule = $user->mailRules()->create(['description' => '合同説明会の宣伝']);
        $email = $this->mail($user);
        $this->fakeOpenAi($this->answer(['category' => 'info', 'decision' => 'unsure', 'rule_id' => $rule->id, 'reason' => '応募済み企業の可能性']));

        app(MailAssessor::class)->assessPending($user, 10);

        $email->refresh();
        $this->assertSame(EmailStatus::New, $email->status);
        $this->assertSame(MailDecision::Unsure, $email->ai_decision);
        $this->assertSame('応募済み企業の可能性', $email->ai_reason);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function action(array $overrides = []): array
    {
        return [
            'kind' => 'create', 'event_id' => null, 'type' => 'interview', 'title' => '一次面接', 'date' => '2026-10-09',
            'start_time' => '14:00', 'end_date' => null, 'end_time' => '15:00', 'location' => null, 'online_url' => null,
            ...$overrides,
        ];
    }

    public function test_calendar_actions_are_cleaned_and_must_point_at_the_users_events(): void
    {
        $user = User::factory()->withGmail()->create();
        $mine = Event::factory()->for($user)->create();
        $past = Event::factory()->for($user)->create(['starts_at' => now()->subMonth()]);
        $foreign = Event::factory()->create();
        $email = $this->mail($user, '一次面接のご案内');
        $this->fakeOpenAi($this->answer([
            'category' => 'schedule',
            'signup_url' => 'https://example.com/entry',
            'actions' => [
                $this->action(['online_url' => 'https://teams.microsoft.com/l/x']),
                $this->action(['type' => 'party', 'title' => '', 'start_time' => '25:00', 'online_url' => 'javascript:alert(1)']),
                $this->action(['date' => '来週']),
                $this->action(['kind' => 'update', 'event_id' => $mine->id, 'date' => '2026-10-10', 'start_time' => null]),
                $this->action(['kind' => 'update', 'event_id' => $foreign->id]),
                $this->action(['kind' => 'cancel', 'event_id' => $past->id, 'date' => null]),
                $this->action(['kind' => 'cancel', 'event_id' => $mine->id, 'date' => null]),
            ],
        ]));

        app(MailAssessor::class)->assessPending($user, 10);

        $email->refresh();
        $actions = $email->ai_actions;
        $this->assertSame(['create', 'create', 'update', 'cancel'], array_column($actions, 'kind'));
        $this->assertSame('https://teams.microsoft.com/l/x', $actions[0]['online_url']);
        $this->assertSame(['other', '予定', null, null], [$actions[1]['type'], $actions[1]['title'], $actions[1]['start_time'], $actions[1]['online_url']]);
        $this->assertSame([$mine->id, $mine->id], [$actions[2]['event_id'], $actions[3]['event_id']]);
        $this->assertFalse($actions[0]['applied']);
        $this->assertSame('https://example.com/entry', $email->ai_signup_url);
        $this->assertSame(MailCategory::Schedule, $email->ai_category);

        // OpenAI was shown the user's upcoming events, not other people's.
        Http::assertSent(fn (Request $r) => str_contains($r['messages'][1]['content'], "[ID {$mine->id}]")
            && ! str_contains($r['messages'][1]['content'], "[ID {$foreign->id}]"));
    }

    public function test_events_that_are_already_over_are_not_offered(): void
    {
        $this->travelTo('2026-10-01 09:00');
        $user = User::factory()->withGmail()->create();
        $email = $this->mail($user);
        $this->fakeOpenAi($this->answer(['actions' => [
            $this->action(['date' => '2026-09-25']),
            $this->action(['date' => '2026-09-28', 'end_date' => '2026-10-02']),
            $this->action(['date' => '2026-10-01']),
        ]]));

        app(MailAssessor::class)->assessPending($user, 10);

        $this->assertSame(['2026-09-28', '2026-10-01'], array_column($email->fresh()->ai_actions, 'date'));
    }

    public function test_mails_that_touch_the_calendar_are_never_excluded(): void
    {
        $user = User::factory()->withGmail()->create();
        $rule = $user->mailRules()->create(['description' => '説明会の案内']);
        $email = $this->mail($user);
        $this->fakeOpenAi($this->answer(['category' => 'info', 'decision' => 'exclude', 'rule_id' => $rule->id, 'actions' => [$this->action()]]));

        app(MailAssessor::class)->assessPending($user, 10);

        $this->assertSame(EmailStatus::New, $email->fresh()->status);
        $this->assertSame(MailDecision::Unsure, $email->fresh()->ai_decision);
    }

    public function test_openai_failures_are_recorded_and_can_be_retried(): void
    {
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limit']], 429)
            ->push(['choices' => [['message' => ['content' => json_encode($this->answer()), 'refusal' => null]]]]),
        ]);
        $user = User::factory()->withGmail()->create();
        $email = $this->mail($user);

        app(MailAssessor::class)->assessPending($user, 10);

        $this->assertSame(MailAiStatus::Failed, $email->fresh()->ai_status);
        $this->assertStringContainsString('rate limit', $email->fresh()->ai_error);

        $this->actingAs($user)->post(route('inbox.assess'))->assertRedirect();
        $this->assertSame(MailAiStatus::Done, $email->fresh()->ai_status);
    }

    public function test_nothing_is_sent_without_an_api_key(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        $user = User::factory()->withGmail()->create();
        $this->mail($user);

        $this->assertSame(0, app(MailAssessor::class)->assessPending($user, 10));
        Http::assertNothingSent();
    }

    public function test_hide_similar_learns_a_rule_and_rechecks_unread_mails(): void
    {
        $user = User::factory()->withGmail()->create();
        $email = $this->mail($user);
        $similar = $this->mail($user);
        $similar->forceFill(['ai_status' => MailAiStatus::Done, 'ai_category' => MailCategory::Ad])->save();
        $interview = $this->mail($user, '一次面接のご案内');
        $interview->forceFill(['ai_status' => MailAiStatus::Done, 'ai_category' => MailCategory::Schedule])->save();
        $this->fakeOpenAi(['description' => '初任給の高さを売りにした早期選考エントリーの宣伝メール']);

        $this->actingAs($user)->post(route('inbox.hide-similar', $email))->assertRedirect();

        $rule = $user->mailRules()->sole();
        $this->assertSame('初任給の高さを売りにした早期選考エントリーの宣伝メール', $rule->description);
        $this->assertSame(EmailStatus::Ignored, $email->fresh()->status);
        $this->assertSame(MailAiStatus::Pending, $similar->fresh()->ai_status);
        $this->assertSame(MailAiStatus::Done, $interview->fresh()->ai_status);
    }

    public function test_deleting_a_rule_brings_its_mails_back(): void
    {
        $user = User::factory()->withGmail()->create();
        $rule = $user->mailRules()->create(['description' => '宣伝']);
        $email = $this->mail($user);
        $email->forceFill(['status' => EmailStatus::Excluded, 'mail_rule_id' => $rule->id])->save();

        $this->actingAs($user)->delete(route('mail-rules.destroy', $rule))->assertRedirect();

        $this->assertModelMissing($rule);
        $this->assertSame(EmailStatus::New, $email->fresh()->status);

        $other = User::factory()->create();
        $foreign = MailRule::forceCreate(['user_id' => $user->id, 'description' => 'x']);
        $this->actingAs($other)->delete(route('mail-rules.destroy', $foreign))->assertNotFound();
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     */
    private function mailWithActions(User $user, array $actions): Email
    {
        $email = $this->mail($user, '面接のご案内');
        $email->forceFill([
            'ai_status' => MailAiStatus::Done,
            'ai_actions' => array_map(fn ($a) => [...$this->action($a), 'applied' => false], $actions),
        ])->save();

        return $email;
    }

    public function test_event_from_a_mail_is_added_after_confirmation(): void
    {
        $user = User::factory()->create();
        $email = $this->mailWithActions($user, [[]]);
        $route = route('inbox.actions.event', ['email' => $email, 'index' => 0]);

        $this->actingAs($user)->post($route, [
            'type' => 'interview',
            'title' => '一次面接',
            'all_day' => false,
            'start_date' => '2026-10-09',
            'start_time' => '14:00',
            'end_time' => '15:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $event = $user->events()->sole();
        $this->assertSame(EventSource::Gmail, $event->source);
        $this->assertSame('2026-10-09 14:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame(EmailStatus::Done, $email->fresh()->status);
        $this->assertTrue($email->fresh()->ai_actions[0]['applied']);

        // A second click does not add it twice.
        $this->actingAs($user)->post($route, [
            'type' => 'interview', 'title' => '一次面接', 'all_day' => true, 'start_date' => '2026-10-09',
        ])->assertStatus(409);
        $this->assertSame(1, $user->events()->count());
    }

    public function test_a_reminder_of_an_event_already_added_shows_it_as_registered(): void
    {
        $user = User::factory()->create();
        $first = $this->mailWithActions($user, [['type' => 'deadline', 'title' => 'ES締切', 'date' => '2026-10-09', 'start_time' => '10:00']]);
        $reminder = $this->mailWithActions($user, [['type' => 'deadline', 'title' => 'ES提出締切', 'date' => '2026-10-09', 'start_time' => '10:00']]);

        $this->actingAs($user)->post(route('inbox.actions.event', ['email' => $first, 'index' => 0]), [
            'type' => 'deadline', 'title' => 'ES締切', 'all_day' => false, 'start_date' => '2026-10-09', 'start_time' => '10:00',
        ])->assertRedirect();

        $this->actingAs($user)->get(route('inbox.index'))->assertInertia(fn ($page) => $page
            ->where('emails', fn ($emails) => collect($emails)->firstWhere('id', $reminder->id)['ai']['actions'][0]['registered'] === true));
    }

    public function test_reschedule_mail_moves_the_event_after_one_click(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'starts_at' => '2026-10-09 14:00',
            'ends_at' => '2026-10-09 15:30',
            'all_day' => false,
        ]);
        $email = $this->mailWithActions($user, [[
            'kind' => 'update', 'event_id' => $event->id, 'date' => '2026-10-12', 'start_time' => '10:00', 'end_time' => null,
            'online_url' => 'https://zoom.us/j/1',
        ]]);

        $this->actingAs($user)->post(route('inbox.actions.apply', ['email' => $email, 'index' => 0]))->assertRedirect();

        $event->refresh();
        $this->assertSame('2026-10-12 10:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 11:30', $event->ends_at->format('Y-m-d H:i'));
        $this->assertSame('https://zoom.us/j/1', $event->online_url);
        $this->assertTrue($email->fresh()->ai_actions[0]['applied']);
    }

    public function test_cancel_mail_cancels_the_event_and_removes_it_from_google(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([], 204)]);
        $user = User::factory()->withGoogleCalendar()->create();
        $event = Event::factory()->for($user)->create(['google_event_id' => 'g1']);
        $email = $this->mailWithActions($user, [['kind' => 'cancel', 'event_id' => $event->id, 'date' => null]]);

        $this->actingAs($user)->post(route('inbox.actions.apply', ['email' => $email, 'index' => 0]))->assertRedirect();

        $this->assertSame(EventStatus::Cancelled, $event->fresh()->status);
        $this->assertNull($event->fresh()->google_event_id);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/g1'));
    }

    public function test_actions_cannot_touch_someone_elses_event(): void
    {
        $user = User::factory()->create();
        $foreign = Event::factory()->create();
        $email = $this->mailWithActions($user, [['kind' => 'cancel', 'event_id' => $foreign->id, 'date' => null]]);

        $this->actingAs($user)->post(route('inbox.actions.apply', ['email' => $email, 'index' => 0]))->assertNotFound();
        $this->assertNotSame(EventStatus::Cancelled, $foreign->fresh()->status);

        $this->actingAs(User::factory()->create())
            ->post(route('inbox.actions.apply', ['email' => $email, 'index' => 0]))->assertNotFound();
    }

    public function test_inbox_page_and_status_changes(): void
    {
        $user = User::factory()->withGmail()->create();
        $email = $this->mail($user);

        $this->actingAs($user)->get(route('inbox.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('inbox/index')->has('emails', 1)->where('ai.pending', 1));

        $this->actingAs($user)->patch(route('inbox.status', $email), ['status' => 'ignored'])->assertRedirect();
        $this->assertSame(EmailStatus::Ignored, $email->fresh()->status);

        $other = User::factory()->create();
        $this->actingAs($other)->patch(route('inbox.status', $email), ['status' => 'new'])->assertNotFound();
        $this->actingAs($other)->post(route('inbox.hide-similar', $email))->assertNotFound();
    }
}
