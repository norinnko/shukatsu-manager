<?php

namespace App\Services\Gmail;

use App\Enums\EmailStatus;
use App\Enums\EventType;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Enums\MailDecision;
use App\Models\Email;
use App\Models\Event;
use App\Models\MailRule;
use App\Models\User;
use App\Services\OpenAi\OpenAiClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Has OpenAI read a mail: what kind it is, whether the user's
 * "次から表示しない" rules hide it, and what it asks to do with the
 * calendar (add a confirmed event, move or cancel an existing one).
 *
 * Whatever the model answers, interview dates, results and deadlines are
 * never hidden, nothing is hidden without one of the user's rules, and
 * calendar actions are only offered: the user applies them with a click.
 */
class MailAssessor
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_CANCEL = 'cancel';

    public function __construct(private OpenAiClient $openAi)
    {
        //
    }

    /**
     * Judge the user's mails that are waiting for it, newest first.
     *
     * @return int Number of mails judged.
     */
    public function assessPending(User $user, int $limit): int
    {
        if (! $this->openAi->configured()) {
            return 0;
        }

        $emails = $user->emails()
            ->where('ai_status', MailAiStatus::Pending)
            ->latest('received_at')
            ->limit($limit)
            ->get();

        if ($emails->isEmpty()) {
            return 0;
        }

        $context = MailContext::forUser($user);

        foreach ($emails as $email) {
            $this->assess($email, $context);
        }

        return $emails->count();
    }

    public function assess(Email $email, MailContext $context): void
    {
        try {
            $answer = $this->openAi->json($this->instructions(), $this->input($email, $context), 'mail_assessment', $this->schema());
        } catch (Throwable $e) {
            Log::warning('Mail assessment failed', ['email_id' => $email->id, 'error' => $e->getMessage()]);

            $email->forceFill(['ai_status' => MailAiStatus::Failed, 'ai_error' => mb_substr($e->getMessage(), 0, 250)])->save();

            return;
        }

        $category = MailCategory::tryFrom((string) ($answer['category'] ?? '')) ?? MailCategory::Other;
        $decision = MailDecision::tryFrom((string) ($answer['decision'] ?? '')) ?? MailDecision::Keep;
        $rule = $context->rules->firstWhere('id', $answer['rule_id'] ?? null);
        $reason = trim((string) ($answer['reason'] ?? ''));
        $actions = $this->actions($answer['actions'] ?? [], $context);

        // Hiding is only ever based on one of the user's own rules.
        if ($rule === null) {
            $decision = MailDecision::Keep;
        }

        // Interview dates, results and deadlines, and anything that touches
        // the calendar, are always shown.
        if ($decision === MailDecision::Exclude && ($category->isImportant() || $actions !== [])) {
            $decision = MailDecision::Unsure;
            $reason = '選考の予定・結果・締切に関する連絡の可能性があるため表示しています。'.$reason;
        }

        $status = $email->status;

        if ($decision === MailDecision::Exclude && $status === EmailStatus::New) {
            $status = EmailStatus::Excluded;
        } elseif ($decision !== MailDecision::Exclude && $status === EmailStatus::Excluded) {
            $status = EmailStatus::New;
        }

        $signupUrl = (string) ($answer['signup_url'] ?? '');

        $email->forceFill([
            'status' => $status,
            'ai_status' => MailAiStatus::Done,
            'ai_category' => $category,
            'ai_decision' => $decision,
            'ai_summary' => mb_substr(trim((string) ($answer['summary'] ?? '')), 0, 500) ?: null,
            'ai_reason' => $decision === MailDecision::Keep ? null : mb_substr($reason, 0, 500),
            'ai_actions' => $actions,
            'ai_signup_url' => preg_match('#^https?://#i', $signupUrl) ? mb_substr($signupUrl, 0, 1024) : null,
            'ai_error' => null,
            'mail_rule_id' => $decision === MailDecision::Keep ? null : $rule?->id,
            'company_id' => $email->company_id
                ?? (filled($answer['company_name'] ?? null) ? $context->companies->byName((string) $answer['company_name']) : null),
        ])->save();
    }

    private function instructions(): string
    {
        $today = now()->isoFormat('YYYY年M月D日（ddd）');

        return <<<TXT
        あなたは日本の就活生（2028年3月卒業予定、IT・セキュリティ業界志望）のメール仕分けアシスタントです。今日は{$today}です。
        与えられたメール1通を読み、JSONで答えてください。

        ## category（メールの種類）
        「本人が応募・予約・エントリー済みか」を区別してください。判断材料：本人の名前宛て、予約・応募・エントリーへのお礼、
        選考ステップ（書類選考・適性検査・面接）の個別の案内、マイページへの個別メッセージなど。一斉配信の募集は「済み」ではありません。
        - schedule: 本人が予約・応募・参加確定済みの予定の連絡（予約完了、前日などのリマインド、日程変更、キャンセル）、本人宛ての面接・面談の日程
        - result: 選考結果、合否、内定、次の選考への案内
        - task: 本人がエントリー済み・選考中の企業からの、本人宛ての対応依頼（ES・書類の提出、受検、日程調整、アンケートなど）。
          提出・回答の受領確認やお礼（対応不要）は info
        - info: 参加・応募を募る案内（説明会・インターン・イベント・早期選考の募集。「締切間近」「エントリーはお早めに」など締切が書いてあっても、一斉配信の募集は info）、企業からのお知らせ
        - ad: 宣伝、メールマガジン、ナビサイトやエージェントのおすすめ・PR
        - other: 就活と関係ないメール（Googleのセキュリティ通知、サービスの登録完了通知など）

        ## decision（表示するか）
        「利用者の除外ルール」に照らして決めます。
        - どのルールにも当てはまらない → keep（rule_id は null）
        - ルールの趣旨にはっきり当てはまる → exclude（rule_id に当てはまったルールのID）
        - ルールに近いが、利用者に必要かもしれない（応募済み企業からの個別の連絡らしい、ルールの内容と一部違う など） → unsure（rule_id に近いルールのID）
        - 迷ったら unsure にしてください
        - ルールが1つも無いときは、必ず keep
        - 差出人のアドレスや送信サービスが同じというだけで判断せず、本文の内容で判断してください
        - 【必須】面接・面談の日程、選考結果、内定、締切・提出依頼、日程調整など、本人の対応が必要な連絡は、ルールに当てはまっても exclude にしない

        ## actions（カレンダーへの反映。利用者がボタンで確認してから反映します）
        - create: 本人の参加が確定した予定（予約完了・参加確定・面接日程の確定）と、本人がエントリー済みの選考の締切・受検期限。
          参加が未定の募集・案内（「ご予約はこちら」「参加者募集」など）は create にしない（代わりに signup_url を入れる）。
          候補日から選ぶ形式（まだ確定していない）なら create にしない。
        - update: 「利用者の登録済みの予定」のどれかの日時・場所・URLが変わった連絡。event_id にその予定のID、変更後の内容を入れる。
        - cancel: 「利用者の登録済みの予定」のどれかが中止・キャンセルになった連絡。event_id にその予定のID。
        - update / cancel は、どの予定のことか確信が持てるときだけ。登録済みの予定に無い予定の変更なら、変更後の内容で create にする。
        - type は interview（面接・面談）/ seminar（説明会）/ intern（インターン）/ test（Webテスト・適性検査）/ deadline（締切）/ other。
        - date は YYYY-MM-DD（年が書かれていなければ受信日時以降の最も近い年）。時刻は HH:MM（24時間）、分からなければ null。cancel では date は null でよい。
        - 今日より前に終わった予定・締切と、「利用者の登録済みの予定」にすでにある予定は actions に入れない。
        - title は短く（例：「一次面接」「ES締切」）。location は会場、online_url は Zoom / Teams / Meet などの参加URL。無ければ null。

        ## そのほか
        - signup_url: 参加が未定の説明会・イベント・インターンの申し込み（予約）ページのURL。無ければ null
        - summary: 何のメールかを1文で（例：「SHIFTから一次面接の日程確定の連絡。10/9 14:00、Teams」）
        - reason: exclude / unsure の理由（どのルールのどこに当てはまるか）。keep なら空文字
        - company_name: 登録企業一覧にある企業からのメールなら一覧の表記そのまま。無ければ差出元の企業名。分からなければ null
        TXT;
    }

    private function input(Email $email, MailContext $context): string
    {
        $ruleLines = $context->rules->isEmpty()
            ? '（なし）'
            : $context->rules->map(fn (MailRule $r) => "- [ID {$r->id}] {$r->description}")->implode("\n");
        $eventLines = $context->events->isEmpty()
            ? '（なし）'
            : $context->events->map(fn (Event $e) => "- [ID {$e->id}] {$e->starts_at->isoFormat($e->all_day ? 'YYYY/MM/DD（終日）' : 'YYYY/MM/DD HH:mm')} {$e->displayTitle()}")->implode("\n");
        $companyNames = implode('、', $context->companies->names()) ?: '（なし）';
        $body = mb_substr((string) ($email->body ?: $email->snippet), 0, (int) config('shukatsu.gmail.ai_body_length'));
        $from = $email->from_name ? "{$email->from_name} <{$email->from_address}>" : $email->from_address;

        return <<<TXT
        # 利用者の除外ルール
        {$ruleLines}

        # 利用者の登録済みの予定（今後）
        {$eventLines}

        # 登録企業
        {$companyNames}

        # メール
        受信日時: {$email->received_at->isoFormat('YYYY/MM/DD HH:mm')}
        差出人: {$from}
        件名: {$email->subject}
        本文:
        {$body}
        TXT;
    }

    /**
     * Keep only well-formed actions; update / cancel must point at one of
     * the user's upcoming events.
     *
     * @return list<array<string, mixed>>
     */
    private function actions(mixed $actions, MailContext $context): array
    {
        $clean = [];
        $today = now()->toDateString();
        $date = fn (mixed $v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $time = fn (mixed $v) => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;

        foreach (is_array($actions) ? $actions : [] as $action) {
            if (! is_array($action)) {
                continue;
            }

            $kind = (string) ($action['kind'] ?? '');
            $eventId = $action['event_id'] ?? null;
            $target = in_array($kind, [self::ACTION_UPDATE, self::ACTION_CANCEL], true)
                ? $context->events->firstWhere('id', $eventId)
                : null;

            $valid = match ($kind) {
                self::ACTION_CREATE, self::ACTION_UPDATE => $date($action['date'] ?? null) !== null && ($kind === self::ACTION_CREATE || $target !== null),
                self::ACTION_CANCEL => $target !== null,
                default => false,
            };

            // Nothing to add or move for something already over.
            $lastDay = $date($action['end_date'] ?? null) ?? $date($action['date'] ?? null);

            if (! $valid || ($kind !== self::ACTION_CANCEL && $lastDay < $today)) {
                continue;
            }

            $url = (string) ($action['online_url'] ?? '');

            $clean[] = [
                'kind' => $kind,
                'event_id' => $target?->id,
                'type' => (EventType::tryFrom((string) ($action['type'] ?? '')) ?? $target->type ?? EventType::Other)->value,
                'title' => mb_substr(trim((string) ($action['title'] ?? '')), 0, 100) ?: ($target->title ?? '予定'),
                'date' => $date($action['date'] ?? null),
                'start_time' => $time($action['start_time'] ?? null),
                'end_date' => $date($action['end_date'] ?? null),
                'end_time' => $time($action['end_time'] ?? null),
                'location' => filled($action['location'] ?? null) ? mb_substr((string) $action['location'], 0, 255) : null,
                'online_url' => preg_match('#^https?://#i', $url) ? mb_substr($url, 0, 1024) : null,
                'applied' => false,
            ];
        }

        return array_slice($clean, 0, 10);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['category', 'summary', 'decision', 'rule_id', 'reason', 'company_name', 'signup_url', 'actions'],
            'properties' => [
                'category' => ['type' => 'string', 'enum' => array_map(fn (MailCategory $c) => $c->value, MailCategory::cases())],
                'summary' => ['type' => 'string'],
                'decision' => ['type' => 'string', 'enum' => array_map(fn (MailDecision $d) => $d->value, MailDecision::cases())],
                'rule_id' => ['type' => ['integer', 'null']],
                'reason' => ['type' => 'string'],
                'company_name' => $nullableString,
                'signup_url' => $nullableString,
                'actions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['kind', 'event_id', 'type', 'title', 'date', 'start_time', 'end_date', 'end_time', 'location', 'online_url'],
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => [self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_CANCEL]],
                            'event_id' => ['type' => ['integer', 'null']],
                            'type' => ['type' => 'string', 'enum' => EventType::values()],
                            'title' => ['type' => 'string'],
                            'date' => $nullableString,
                            'start_time' => $nullableString,
                            'end_date' => $nullableString,
                            'end_time' => $nullableString,
                            'location' => $nullableString,
                            'online_url' => $nullableString,
                        ],
                    ],
                ],
            ],
        ];
    }
}
