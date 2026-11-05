<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Line\LineClient;
use App\Services\Line\LineNotifier;
use Illuminate\Console\Command;
use Throwable;

class LineNotify extends Command
{
    /**
     * @var string
     */
    protected $signature = 'shukatsu:line {kind : morning (daily summary) or mail (new important mails)}';

    /**
     * @var string
     */
    protected $description = 'Send LINE notifications to every linked user';

    public function handle(LineClient $line, LineNotifier $notifier): int
    {
        $kind = (string) $this->argument('kind');

        if (! in_array($kind, ['morning', 'mail'], true)) {
            $this->error('kind must be "morning" or "mail"');

            return self::INVALID;
        }

        if (! $line->configured()) {
            return self::SUCCESS;
        }

        foreach (User::whereNotNull('line_user_id')->get() as $user) {
            try {
                $result = $kind === 'morning'
                    ? ($notifier->sendMorning($user) ? 'morning summary sent' : 'nothing to send')
                    : $notifier->sendNewMails($user).' mail(s) sent';

                $this->info("{$user->email}: {$result}");
            } catch (Throwable $e) {
                report($e);
                $this->warn("{$user->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
