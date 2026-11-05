<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Gmail\MailAssessor;
use Illuminate\Console\Command;

class AssessMail extends Command
{
    /**
     * @var string
     */
    protected $signature = 'shukatsu:assess-mail {--limit= : Mails per user (defaults to config shukatsu.gmail.assess_batch)}';

    /**
     * @var string
     */
    protected $description = 'Have OpenAI judge imported mails that are waiting for it';

    public function handle(MailAssessor $assessor): int
    {
        $limit = (int) ($this->option('limit') ?: config('shukatsu.gmail.assess_batch'));

        foreach (User::whereHas('emails', fn ($q) => $q->where('ai_status', 'pending'))->get() as $user) {
            $this->info("{$user->email}: ".$assessor->assessPending($user, $limit).' mail(s) judged');
        }

        return self::SUCCESS;
    }
}
