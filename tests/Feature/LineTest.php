<?php

namespace Tests\Feature;

use App\Enums\EmailStatus;
use App\Enums\EventSource;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Models\Email;
use App\Models\Event;
use App\Models\User;
use App\Services\Line\LineNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineTest extends TestCase
{
    use RefreshDatabase;

    private const LINE_USER = 'U0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.line.channel_secret' => 'secret',
            'services.line.channel_access_token' => 'token',
            'services.line.reserve' => 35,
        ]);
    }

    private function fakeLine(int $remaining = 150): void
    {
        Http::fake([
            'api.line.me/v2/bot/message/quota/consumption' => Http::response(['totalUsage' => 200 - $remaining]),
            'api.line.me/v2/bot/message/quota' => Http::response(['type' => 'limited', 'value' => 200]),
            'api.line.me/*' => Http::response([]),
            'www.googleapis.com/*' => Http::response(['id' => 'g1']),
        ]);
    }

    private function linkedUser(): User
    {
        return User::factory()->create(['line_user_id' => self::LINE_USER, 'line_linked_at' => now()->subHour()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function judgedMail(User $user, array $attributes = []): Email
    {
        $email = $user->emails()->create([
            'gmail_message_id' => uniqid('m'),
            'from_address' => 'saiyo@example.co.jp',
            'subject' => '一次面接のご案内',
            'received_at' => now(),
        ]);
        $email->forceFill([
            'ai_status' => MailAiStatus::Done,
            'ai_category' => MailCategory::Schedule,
            'ai_summary' => '一次面接の日程確定',
            'ai_actions' => [[
                'kind' => 'create', 'event_id' => null, 'type' => 'interview', 'title' => '一次面接', 'date' => '2026-10-09',
                'start_time' => '14:00', 'end_date' => null, 'end_time' => '15:00', 'location' => null,
                'online_url' => 'https://zoom.us/j/1', 'applied' => false,
            ]],
            ...$attributes,
        ])->save();

        return $email;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function webhook(array $event, ?string $secret = 'secret')
    {
        $body = json_encode(['events' => [[
            'replyToken' => 'reply-token',
            'source' => ['userId' => self::LINE_USER],
            ...$event,
        ]]]);

        return $this->call('POST', route('line.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => base64_encode(hash_hmac('sha256', (string) $body, (string) $secret, true)),
        ], $body);
    }

    public function test_requests_without_a_valid_signature_are_rejected(): void
    {
        $this->fakeLine();
        $user = $this->linkedUser();
        $email = $this->judgedMail($user);

        $this->webhook(['type' => 'postback', 'postback' => ['data' => "op=ignore&email={$email->id}"]], secret: 'wrong')
            ->assertStatus(400);

        $this->assertSame(EmailStatus::New, $email->fresh()->status);
    }

    public function test_add_button_creates_the_suggested_event(): void
    {
        $this->travelTo('2026-10-01 09:00');
        $this->fakeLine();
        $user = $this->linkedUser();
        $email = $this->judgedMail($user);

        $this->webhook(['type' => 'postback', 'postback' => ['data' => "op=add&email={$email->id}&index=0"]])->assertOk();

        $event = $user->events()->sole();
        $this->assertSame(EventSource::Gmail, $event->source);
        $this->assertSame('2026-10-09 14:00 15:00', $event->starts_at->format('Y-m-d H:i').' '.$event->ends_at->format('H:i'));
        $this->assertSame('https://zoom.us/j/1', $event->online_url);
        $this->assertSame(EmailStatus::Done, $email->fresh()->status);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/message/reply')
            && $r['replyToken'] === 'reply-token' && str_contains($r['messages'][0]['text'], '予定を追加しました'));

        // Pressing the button again does not add it twice.
        $this->webhook(['type' => 'postback', 'postback' => ['data' => "op=add&email={$email->id}&index=0"]])->assertOk();
        $this->assertSame(1, $user->events()->count());
    }

    public function test_ignore_button(): void
    {
        $this->fakeLine();
        $user = $this->linkedUser();
        $email = $this->judgedMail($user);

        $this->webhook(['type' => 'postback', 'postback' => ['data' => "op=ignore&email={$email->id}"]])->assertOk();

        $this->assertSame(EmailStatus::Ignored, $email->fresh()->status);
    }

    public function test_events_from_other_line_accounts_or_for_other_users_mails_do_nothing(): void
    {
        $this->fakeLine();
        $user = $this->linkedUser();
        $stranger = User::factory()->create();
        $foreignMail = $this->judgedMail($stranger);

        $this->webhook(['type' => 'postback', 'postback' => ['data' => "op=ignore&email={$foreignMail->id}"]])->assertOk();
        $this->assertSame(EmailStatus::New, $foreignMail->fresh()->status);

        $own = $this->judgedMail($user);
        $body = json_encode(['events' => [[
            'type' => 'postback', 'replyToken' => 'r', 'source' => ['userId' => 'Uffffffffffffffffffffffffffffffff'],
            'postback' => ['data' => "op=ignore&email={$own->id}"],
        ]]]);
        $this->call('POST', route('line.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => base64_encode(hash_hmac('sha256', (string) $body, 'secret', true)),
        ], $body)->assertOk();

        $this->assertSame(EmailStatus::New, $own->fresh()->status);
    }

    public function test_new_important_mails_are_pushed_once_as_a_carousel(): void
    {
        $this->travelTo('2026-10-01 09:00');
        $this->fakeLine();
        $user = $this->linkedUser();
        $interview = $this->judgedMail($user);
        $result = $this->judgedMail($user, ['ai_category' => MailCategory::Result, 'ai_actions' => [], 'subject' => '選考結果のご連絡']);
        $this->judgedMail($user, ['ai_category' => MailCategory::Info, 'ai_actions' => [], 'subject' => '説明会のご案内']);
        $old = $this->judgedMail($user);
        $old->forceFill(['received_at' => now()->subDay()])->save();
        $pending = $this->judgedMail($user, ['ai_status' => MailAiStatus::Pending]);

        $sent = app(LineNotifier::class)->sendNewMails($user);

        $this->assertSame(2, $sent);
        Http::assertSent(function (Request $r) use ($interview) {
            if (! str_contains($r->url(), '/message/push')) {
                return false;
            }
            $bubbles = $r['messages'][0]['contents']['contents'];

            return $r['to'] === self::LINE_USER && count($bubbles) === 2
                && $bubbles[0]['footer']['contents'][0]['action']['data'] === http_build_query(['op' => 'add', 'email' => $interview->id, 'index' => 0]);
        });
        $this->assertNotNull($interview->fresh()->line_notified_at);
        $this->assertNotNull($result->fresh()->line_notified_at);
        $this->assertNull($old->fresh()->line_notified_at);
        $this->assertNull($pending->fresh()->line_notified_at);

        // Nothing new: nothing is pushed.
        $this->assertSame(0, app(LineNotifier::class)->sendNewMails($user));
    }

    public function test_mail_pushes_stop_when_only_the_reserve_is_left(): void
    {
        $this->fakeLine(remaining: 35);
        $user = $this->linkedUser();
        $email = $this->judgedMail($user);

        $this->assertSame(0, app(LineNotifier::class)->sendNewMails($user));
        $this->assertNull($email->fresh()->line_notified_at);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/message/push'));
    }

    public function test_morning_summary(): void
    {
        $today = CarbonImmutable::parse('2026-10-01 07:00');
        $user = $this->linkedUser();
        Event::factory()->for($user)->create(['title' => '一次面接', 'starts_at' => '2026-10-01 14:00', 'ends_at' => '2026-10-01 15:00', 'all_day' => false, 'online_url' => 'https://zoom.us/j/1']);
        Event::factory()->for($user)->create(['title' => 'ES締切', 'starts_at' => '2026-10-04 10:00', 'all_day' => false]);
        Event::factory()->for($user)->create(['title' => '説明会', 'starts_at' => '2026-10-06 10:00', 'all_day' => false]);
        $this->judgedMail($user);

        $text = app(LineNotifier::class)->morningText($user, $today);

        $this->assertStringContainsString('14:00〜15:00', $text);
        $this->assertStringContainsString('https://zoom.us/j/1', $text);
        $this->assertStringContainsString('あと3日', $text);
        $this->assertStringNotContainsString('説明会', $text); // 5 days ahead: no reminder
        $this->assertStringContainsString('未確認 1件', $text);

        $empty = User::factory()->create(['line_user_id' => self::LINE_USER]);
        $this->assertNull(app(LineNotifier::class)->morningText($empty, $today));
    }

    public function test_linking_only_accepts_a_line_user_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('line.update'), ['line_user_id' => 'not-an-id'])->assertSessionHasErrors('line_user_id');

        $this->actingAs($user)->put(route('line.update'), ['line_user_id' => self::LINE_USER])->assertSessionHasNoErrors();
        $this->assertSame(self::LINE_USER, $user->fresh()->line_user_id);
        $this->assertNotNull($user->fresh()->line_linked_at);
    }
}
