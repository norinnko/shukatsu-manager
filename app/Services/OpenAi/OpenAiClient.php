<?php

namespace App\Services\OpenAi;

use Illuminate\Support\Facades\Http;

/**
 * Minimal OpenAI Chat Completions client that returns JSON matching a
 * schema (Structured Outputs). The model is chosen in .env (OPENAI_MODEL).
 */
class OpenAiClient
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    public function configured(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function model(): string
    {
        return (string) config('services.openai.model');
    }

    /**
     * @param  array<string, mixed>  $schema  JSON Schema of the answer (strict)
     * @return array<string, mixed>
     */
    public function json(string $instructions, string $input, string $name, array $schema): array
    {
        if (! $this->configured()) {
            throw new OpenAiException('OpenAIのAPIキーが設定されていません（.env の SHUKATSU_OPENAI_API_KEY）。');
        }

        $response = Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->timeout(90)
            ->post(self::URL, array_filter([
                'model' => $this->model(),
                'reasoning_effort' => config('services.openai.reasoning_effort') ?: null,
                'messages' => [
                    ['role' => 'developer', 'content' => $instructions],
                    ['role' => 'user', 'content' => $input],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => $name, 'strict' => true, 'schema' => $schema],
                ],
            ]));

        if ($response->failed()) {
            throw new OpenAiException('OpenAIの呼び出しに失敗しました（'.$response->status().'）: '
                .mb_substr((string) ($response->json('error.message') ?? $response->body()), 0, 200));
        }

        $message = $response->json('choices.0.message');

        if (! empty($message['refusal'])) {
            throw new OpenAiException('OpenAIが回答を拒否しました: '.$message['refusal']);
        }

        $answer = json_decode((string) ($message['content'] ?? ''), true);

        if (! is_array($answer)) {
            throw new OpenAiException('OpenAIの回答を読み取れませんでした。');
        }

        return $answer;
    }
}
