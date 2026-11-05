<?php

namespace App\Services\Line;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * LINE Messaging API: push / reply messages and the monthly quota.
 *
 * Replies (answers to a button press or message) are free; pushes count
 * against the free plan's monthly limit.
 */
class LineClient
{
    private const API = 'https://api.line.me/v2/bot';

    public function configured(): bool
    {
        return filled(config('services.line.channel_secret')) && filled(config('services.line.channel_access_token'));
    }

    /**
     * Whether a webhook request really comes from LINE.
     */
    public function validSignature(string $body, ?string $signature): bool
    {
        $secret = (string) config('services.line.channel_secret');

        return $secret !== '' && $signature !== null
            && hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function push(string $to, array $messages): void
    {
        $this->send('/message/push', ['to' => $to, 'messages' => $messages]);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function reply(string $replyToken, array $messages): void
    {
        $this->send('/message/reply', ['replyToken' => $replyToken, 'messages' => $messages]);
    }

    /**
     * Push messages left this month, or null when the plan has no limit.
     */
    public function remainingPushes(): ?int
    {
        $quota = $this->client()->get(self::API.'/message/quota');
        $usage = $this->client()->get(self::API.'/message/quota/consumption');

        if ($quota->failed() || $usage->failed()) {
            throw new LineException('LINEの送信可能数を確認できませんでした（'.$quota->status().'）。');
        }

        if ($quota->json('type') !== 'limited') {
            return null;
        }

        return max(0, (int) $quota->json('value') - (int) $usage->json('totalUsage'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload): void
    {
        if (! $this->configured()) {
            throw new LineException('LINEのチャネルが設定されていません（.env の LINE_CHANNEL_SECRET / LINE_CHANNEL_ACCESS_TOKEN）。');
        }

        $response = $this->client()->post(self::API.$path, $payload);

        if ($response->failed()) {
            throw new LineException('LINEへの送信に失敗しました（'.$response->status().'）: '
                .mb_substr((string) ($response->json('message') ?? $response->body()), 0, 200));
        }
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.line.channel_access_token'))
            ->acceptJson()
            ->timeout(15);
    }
}
