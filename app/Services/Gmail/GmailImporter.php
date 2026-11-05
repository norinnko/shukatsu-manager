<?php

namespace App\Services\Gmail;

use App\Models\User;
use App\Services\Google\GoogleNotConnectedException;
use App\Services\Google\GoogleTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use ValueError;

/**
 * Pulls new mails from Gmail (read-only) into the app. Every mail except
 * spam / trash / sent / drafts is kept; MailAssessor judges them afterwards.
 */
class GmailImporter
{
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    /** Pause between message downloads, to stay under Gmail's rate limit. */
    private const PACE_MS = 100;

    /** Base wait before retrying a rate-limited request (grows each attempt). */
    private const RETRY_WAIT_MS = 5000;

    /** Upper bound of message ids looked at in one run. */
    private const MAX_MESSAGES = 1000;

    public function __construct(private GoogleTokenService $tokens)
    {
        //
    }

    /**
     * Import, recording any failure on the user instead of throwing.
     *
     * @return int|null Number of mails stored, or null on failure.
     */
    public function tryImport(User $user): ?int
    {
        try {
            return $this->import($user);
        } catch (Throwable $e) {
            Log::warning('Gmail import failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            $message = $e instanceof GoogleNotConnectedException
                ? $e->getMessage()
                : 'Gmailの取り込みに失敗しました: '.$e->getMessage();

            $user->forceFill(['gmail_sync_error' => mb_substr($message, 0, 250)])->save();

            return null;
        }
    }

    /**
     * @return int Number of mails stored.
     */
    public function import(User $user): int
    {
        $companies = CompanyMatcher::forUser($user);
        $ignoreLabels = (array) config('shukatsu.gmail.ignore_labels');

        [$ids, $historyId] = $user->gmail_history_id === null
            ? $this->recentIds($user)
            : $this->idsSince($user, $user->gmail_history_id);

        $known = $user->emails()->whereIn('gmail_message_id', $ids)->pluck('gmail_message_id')->all();
        $stored = 0;

        foreach (array_diff($ids, $known) as $id) {
            usleep(self::PACE_MS * 1000);
            $message = $this->message($user, $id);

            if ($message === null || array_intersect($message['labelIds'] ?? [], $ignoreLabels) !== []) {
                continue;
            }

            [$fromName, $fromAddress] = $this->parseFrom($this->header($message, 'From') ?? '');
            $subject = $this->header($message, 'Subject') ?: '（件名なし）';

            $user->emails()->create([
                'company_id' => $companies->guess($fromAddress, $fromName, $subject),
                'gmail_message_id' => $id,
                'gmail_thread_id' => $message['threadId'] ?? null,
                'from_address' => mb_substr($fromAddress, 0, 255),
                'from_name' => $fromName !== null ? mb_substr($fromName, 0, 255) : null,
                'subject' => mb_substr($subject, 0, 512),
                'snippet' => html_entity_decode((string) ($message['snippet'] ?? ''), ENT_QUOTES | ENT_HTML5),
                'body' => mb_substr($this->bodyText($message['payload'] ?? []), 0, (int) config('shukatsu.gmail.max_body_length')),
                'received_at' => CarbonImmutable::createFromTimestampMs((int) ($message['internalDate'] ?? 0))
                    ->setTimezone(config('app.timezone')),
            ]);

            $stored++;
        }

        $user->forceFill([
            'gmail_history_id' => $historyId,
            'gmail_synced_at' => now(),
            'gmail_sync_error' => null,
        ])->save();

        return $stored;
    }

    /**
     * First run: mails of the last few days, and the current history cursor.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function recentIds(User $user): array
    {
        // Take the cursor first so nothing arriving during the listing is lost.
        $historyId = (string) $this->client($user)->get(self::API.'/profile')->throw()->json('historyId');
        $ids = [];
        $pageToken = null;

        do {
            $page = $this->client($user)->get(self::API.'/messages', array_filter([
                'q' => 'newer_than:'.(int) config('shukatsu.gmail.initial_days').'d',
                'maxResults' => 100,
                'pageToken' => $pageToken,
            ]))->throw();

            foreach ($page->json('messages') ?? [] as $message) {
                $ids[] = (string) $message['id'];
            }

            $pageToken = $page->json('nextPageToken');
        } while ($pageToken !== null && count($ids) < self::MAX_MESSAGES);

        return [array_values(array_unique($ids)), $historyId];
    }

    /**
     * Later runs: mails added since the stored history cursor.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function idsSince(User $user, string $startHistoryId): array
    {
        $ids = [];
        $historyId = $startHistoryId;
        $pageToken = null;

        do {
            $page = $this->client($user)->get(self::API.'/history', array_filter([
                'startHistoryId' => $startHistoryId,
                'historyTypes' => 'messageAdded',
                'maxResults' => 500,
                'pageToken' => $pageToken,
            ]));

            // The cursor is too old (Gmail keeps about a week): start over.
            if ($page->status() === 404) {
                return $this->recentIds($user);
            }

            $page->throw();

            foreach ($page->json('history') ?? [] as $history) {
                foreach ($history['messagesAdded'] ?? [] as $added) {
                    $ids[] = (string) $added['message']['id'];
                }
            }

            $historyId = (string) ($page->json('historyId') ?? $historyId);
            $pageToken = $page->json('nextPageToken');
        } while ($pageToken !== null && count($ids) < self::MAX_MESSAGES);

        return [array_values(array_unique($ids)), $historyId];
    }

    /**
     * The full message, or null when it no longer exists (deleted or
     * purged between arriving and being fetched).
     *
     * @return array<string, mixed>|null
     *
     * @throws RequestException
     */
    private function message(User $user, string $id): ?array
    {
        $response = $this->client($user)->get(self::API.'/messages/'.rawurlencode($id), ['format' => 'full']);

        return $response->status() === 404 ? null : $response->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function header(array $message, string $name): ?string
    {
        foreach ($message['payload']['headers'] ?? [] as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                return trim((string) $header['value']);
            }
        }

        return null;
    }

    /**
     * "山田 <a@example.com>" => ["山田", "a@example.com"].
     *
     * @return array{0: string|null, 1: string}
     */
    private function parseFrom(string $from): array
    {
        if (preg_match('/^\s*"?(.*?)"?\s*<([^>]+)>\s*$/u', $from, $m)) {
            return [$m[1] !== '' ? $m[1] : null, strtolower(trim($m[2]))];
        }

        return [null, strtolower(trim($from))];
    }

    /**
     * Plain text of the mail: the text/plain part, or the HTML part as text.
     *
     * @param  array<string, mixed>  $payload
     */
    public function bodyText(array $payload): string
    {
        $plain = $this->findPart($payload, 'text/plain');

        if ($plain !== null) {
            return $this->normalize($this->decodePart($plain));
        }

        $html = $this->findPart($payload, 'text/html');

        return $html !== null ? $this->normalize($this->htmlToText($this->decodePart($html))) : '';
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>|null
     */
    private function findPart(array $part, string $mimeType): ?array
    {
        $isAttachment = ($part['filename'] ?? '') !== '';

        if (! $isAttachment && strcasecmp($part['mimeType'] ?? '', $mimeType) === 0 && isset($part['body']['data'])) {
            return $part;
        }

        foreach ($part['parts'] ?? [] as $child) {
            if (($found = $this->findPart($child, $mimeType)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Gmail returns the part's bytes in its original charset, so Japanese
     * mails in ISO-2022-JP / Shift_JIS are converted to UTF-8 here.
     *
     * @param  array<string, mixed>  $part
     */
    private function decodePart(array $part): string
    {
        $bytes = (string) base64_decode(strtr((string) $part['body']['data'], '-_', '+/'));
        $contentType = $this->header(['payload' => $part], 'Content-Type') ?? '';
        $charset = preg_match('/charset="?([^";\s]+)/i', $contentType, $m) ? strtolower($m[1]) : 'utf-8';

        $encoding = match ($charset) {
            'utf-8', 'utf8', 'us-ascii' => null,
            'iso-2022-jp', 'iso-2022-jp-2' => 'ISO-2022-JP-MS',
            'shift_jis', 'shift-jis', 'sjis', 'x-sjis', 'windows-31j', 'cp932' => 'SJIS-win',
            'euc-jp' => 'eucJP-win',
            default => $charset,
        };

        if ($encoding !== null) {
            try {
                $bytes = mb_convert_encoding($bytes, 'UTF-8', $encoding) ?: $bytes;
            } catch (ValueError) {
                // Unknown charset: keep the bytes as they are.
            }
        }

        return mb_scrub($bytes, 'UTF-8');
    }

    private function htmlToText(string $html): string
    {
        $html = (string) preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6]|table)>#i', "\n", $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = (string) preg_replace('/[ \t]+\n/u', "\n", $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Gmail limits requests per user per second / minute: on a rate-limit
     * answer (429, or 403 "quota exceeded") wait and try again.
     */
    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($user))
            ->acceptJson()
            ->timeout(20)
            ->retry(4, fn (int $attempt) => $attempt * self::RETRY_WAIT_MS, function (Throwable $e) {
                $response = $e instanceof RequestException ? $e->response : null;

                return $response !== null && ($response->status() === 429
                    || ($response->status() === 403 && str_contains($response->body(), 'uota')));
            }, throw: false);
    }
}
