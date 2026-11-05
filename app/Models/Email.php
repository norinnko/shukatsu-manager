<?php

namespace App\Models;

use App\Enums\EmailStatus;
use App\Enums\MailAiStatus;
use App\Enums\MailCategory;
use App\Enums\MailDecision;
use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mail imported from the user's Gmail.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property string $gmail_message_id
 * @property string|null $gmail_thread_id
 * @property string $from_address
 * @property string|null $from_name
 * @property string $subject
 * @property string|null $snippet
 * @property string|null $body
 * @property CarbonImmutable $received_at
 * @property EmailStatus $status
 * @property MailAiStatus $ai_status
 * @property MailCategory|null $ai_category
 * @property MailDecision|null $ai_decision
 * @property string|null $ai_summary
 * @property string|null $ai_reason
 * @property list<array<string, mixed>>|null $ai_actions add / update / cancel an event, see MailAssessor
 * @property string|null $ai_signup_url
 * @property string|null $ai_error
 * @property int|null $mail_rule_id
 * @property CarbonImmutable|null $line_notified_at
 * @property-read Company|null $company
 * @property-read MailRule|null $mailRule
 */
#[Fillable([
    'company_id', 'gmail_message_id', 'gmail_thread_id', 'from_address', 'from_name',
    'subject', 'snippet', 'body', 'received_at', 'status',
])]
class Email extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'received_at' => 'immutable_datetime',
            'status' => EmailStatus::class,
            'ai_status' => MailAiStatus::class,
            'ai_category' => MailCategory::class,
            'ai_decision' => MailDecision::class,
            'ai_actions' => 'array',
            'line_notified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<MailRule, $this> */
    public function mailRule(): BelongsTo
    {
        return $this->belongsTo(MailRule::class);
    }

    /** Link that opens the mail in Gmail, in the given signed-in account. */
    public function gmailUrl(string $account): string
    {
        return 'https://mail.google.com/mail/?authuser='.rawurlencode($account).'#all/'.$this->gmail_message_id;
    }
}
