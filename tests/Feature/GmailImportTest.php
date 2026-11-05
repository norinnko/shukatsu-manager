<?php

namespace Tests\Feature;

use App\Enums\EmailStatus;
use App\Enums\MailAiStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Gmail\CompanyMatcher;
use App\Services\Gmail\GmailImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, array{from: string, subject: string, labels?: list<string>, body?: string, charset?: string}>  $messages
     * @param  array<string, mixed>|null  $history  null = history expired (404)
     */
    private function fakeGmail(array $messages, ?array $history = []): void
    {
        Http::fake(function (Request $request) use ($messages, $history) {
            $url = $request->url();

            if (str_contains($url, '/users/me/profile')) {
                return Http::response(['historyId' => '500']);
            }

            if (str_contains($url, '/users/me/history')) {
                return $history === null ? Http::response([], 404) : Http::response($history);
            }

            if (preg_match('#/users/me/messages/([^?]+)\?format=full#', $url, $m)) {
                if (! isset($messages[$m[1]])) {
                    return Http::response(['error' => ['code' => 404, 'message' => 'Requested entity was not found.']], 404);
                }

                $message = $messages[$m[1]];
                $charset = $message['charset'] ?? 'UTF-8';
                $bytes = mb_convert_encoding($message['body'] ?? '', $charset, 'UTF-8');

                return Http::response([
                    'id' => $m[1],
                    'threadId' => "t-{$m[1]}",
                    'labelIds' => $message['labels'] ?? ['INBOX'],
                    'snippet' => 'snippet &amp; more',
                    'internalDate' => '1790000000000',
                    'payload' => [
                        'mimeType' => 'multipart/alternative',
                        'headers' => [['name' => 'From', 'value' => $message['from']], ['name' => 'Subject', 'value' => $message['subject']]],
                        'parts' => [[
                            'mimeType' => 'text/plain',
                            'filename' => '',
                            'headers' => [['name' => 'Content-Type', 'value' => "text/plain; charset=\"{$charset}\""]],
                            'body' => ['data' => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=')],
                        ]],
                    ],
                ]);
            }

            if (str_contains($url, '/users/me/messages')) {
                return Http::response(['messages' => array_map(fn ($id) => ['id' => $id], array_keys($messages))]);
            }

            return Http::response([], 500);
        });
    }

    public function test_first_import_keeps_every_mail_except_spam_and_sent(): void
    {
        $this->fakeGmail([
            'm1' => [
                'from' => '"株式会社サンプル 採用担当" <noreply@sample.axol.jp>',
                'subject' => '一次面接のご案内',
                'body' => "藤井様\n10月9日 14:00から一次面接を行います。",
                'charset' => 'ISO-2022-JP',
            ],
            'm2' => ['from' => 'news@ad.example.com', 'subject' => 'インターン特集', 'labels' => ['CATEGORY_PROMOTIONS']],
            'm3' => ['from' => 'friend@gmail.com', 'subject' => '今度の飲み会'],
            'm4' => ['from' => 'spam@example.com', 'subject' => '当選しました', 'labels' => ['SPAM']],
            'm5' => ['from' => 'me@example.com', 'subject' => 'Re: 面接', 'labels' => ['SENT']],
        ]);
        $user = User::factory()->withGmail()->create();

        $stored = app(GmailImporter::class)->import($user);

        $this->assertSame(3, $stored);
        $this->assertEqualsCanonicalizing(['m1', 'm2', 'm3'], $user->emails()->pluck('gmail_message_id')->all());

        $email = $user->emails()->where('gmail_message_id', 'm1')->sole();
        $this->assertSame('noreply@sample.axol.jp', $email->from_address);
        $this->assertSame('株式会社サンプル 採用担当', $email->from_name);
        $this->assertStringContainsString('10月9日 14:00から一次面接', $email->body);
        $this->assertSame('snippet & more', $email->snippet);
        $this->assertSame(EmailStatus::New, $email->status);
        $this->assertSame(MailAiStatus::Pending, $email->fresh()->ai_status);
        $this->assertSame('500', $user->fresh()->gmail_history_id);
    }

    public function test_next_import_reads_history_and_guesses_the_company(): void
    {
        $user = User::factory()->withGmail()->create(['gmail_history_id' => '100']);
        $company = Company::factory()->for($user)->create([
            'name' => 'サンプル',
            'recruit_url' => 'https://www.sample.co.jp/recruit/',
        ]);
        $this->fakeGmail(
            ['m4' => ['from' => 'jinji@mail.sample.co.jp', 'subject' => 'ご連絡', 'body' => '本文']],
            ['history' => [['messagesAdded' => [['message' => ['id' => 'm4']]]]], 'historyId' => '120'],
        );

        app(GmailImporter::class)->import($user);

        $this->assertSame($company->id, $user->emails()->sole()->company_id);
        $this->assertSame('120', $user->fresh()->gmail_history_id);
    }

    public function test_mails_deleted_before_they_are_fetched_are_skipped(): void
    {
        $user = User::factory()->withGmail()->create(['gmail_history_id' => '100']);
        $this->fakeGmail(
            ['m7' => ['from' => 'a@example.com', 'subject' => '面接のご案内']],
            ['history' => [['messagesAdded' => [['message' => ['id' => 'gone']], ['message' => ['id' => 'm7']]]]], 'historyId' => '130'],
        );

        $this->assertSame(1, app(GmailImporter::class)->import($user));
        $this->assertSame('m7', $user->emails()->sole()->gmail_message_id);
        $this->assertSame('130', $user->fresh()->gmail_history_id);
    }

    public function test_expired_history_falls_back_to_recent_mails(): void
    {
        $user = User::factory()->withGmail()->create(['gmail_history_id' => '1']);
        $this->fakeGmail(['m5' => ['from' => 'a@example.com', 'subject' => '選考結果のご連絡']], history: null);

        app(GmailImporter::class)->import($user);

        $this->assertSame('m5', $user->emails()->sole()->gmail_message_id);
        $this->assertSame('500', $user->fresh()->gmail_history_id);
    }

    public function test_failures_are_recorded_on_the_user(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
        $user = User::factory()->withGmail()->create();

        $this->assertNull(app(GmailImporter::class)->tryImport($user));
        $this->assertStringContainsString('Gmailの取り込みに失敗しました', $user->fresh()->gmail_sync_error);
    }

    public function test_company_matching(): void
    {
        $this->assertSame('sample.co.jp', CompanyMatcher::registrableDomain('recruit.www.sample.co.jp'));
        $this->assertSame('example.com', CompanyMatcher::registrableDomain('www.example.com'));

        $user = User::factory()->create();
        $company = Company::factory()->for($user)->create(['name' => 'デロイト トーマツ サイバー合同会社']);

        // Spaces are ignored when matching names.
        $this->assertSame($company->id, CompanyMatcher::forUser($user)->byName('【デロイトトーマツサイバー合同会社】説明会'));
    }
}
