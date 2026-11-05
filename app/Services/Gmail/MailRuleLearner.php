<?php

namespace App\Services\Gmail;

use App\Enums\EmailStatus;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Models\Email;
use App\Models\MailRule;
use App\Services\OpenAi\OpenAiClient;

/**
 * Turns a mail the user marked "次から表示しない" into a rule describing
 * the kind of mail to hide from now on.
 */
class MailRuleLearner
{
    public function __construct(private OpenAiClient $openAi)
    {
        //
    }

    public function learn(Email $email): MailRule
    {
        $user = $email->user;
        $existing = $user->mailRules()->pluck('description')->map(fn ($d) => "- {$d}")->implode("\n") ?: '（なし）';
        $body = mb_substr((string) ($email->body ?: $email->snippet), 0, (int) config('shukatsu.gmail.ai_body_length'));
        $from = $email->from_name ? "{$email->from_name} <{$email->from_address}>" : $email->from_address;

        $answer = $this->openAi->json(
            <<<'TXT'
            就活生が、次のメールに「次から表示しない」を押しました。
            今後届くメールのうち、これと同じ種類のものを除外するためのルールを日本語で1〜3文で書いてください。

            - 内容の特徴（何の宣伝・案内か、定型文の特徴、どのサービス・企業から来る何のメールか）で表現する
            - 差出人のアドレスだけを条件にしない（同じ差出人から大事な連絡が来ることがあるため）
            - 「〜のような宣伝・案内メール」のように、このメールと同じ種類に絞った書き方にする
            - 面接の日程・選考結果・締切の連絡まで含むような広すぎる書き方はしない
            TXT,
            "# 既にあるルール\n{$existing}\n\n# メール\n差出人: {$from}\n件名: {$email->subject}\n本文:\n{$body}",
            'mail_rule',
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['description'],
                'properties' => ['description' => ['type' => 'string']],
            ],
        );

        $rule = $user->mailRules()->create([
            'description' => mb_substr(trim((string) ($answer['description'] ?? '')), 0, 1000),
            'example_subject' => mb_substr($email->subject, 0, 512),
            'source_email_id' => $email->id,
        ]);

        $email->forceFill(['status' => EmailStatus::Ignored, 'mail_rule_id' => $rule->id])->save();

        // Judge the unread mails again with the new rule. Important ones
        // can never be hidden, so they need no second look.
        $user->emails()
            ->where('status', EmailStatus::New)
            ->where('ai_status', MailAiStatus::Done)
            ->whereNotIn('ai_category', array_map(
                fn (MailCategory $c) => $c->value,
                array_filter(MailCategory::cases(), fn (MailCategory $c) => $c->isImportant()),
            ))
            ->update(['ai_status' => MailAiStatus::Pending]);

        return $rule;
    }
}
