<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Gmail\GmailImporter;
use Illuminate\Console\Command;

class FetchGmail extends Command
{
    /**
     * @var string
     */
    protected $signature = 'shukatsu:fetch-mail';

    /**
     * @var string
     */
    protected $description = 'Import new job-hunting mails from Gmail for every connected user';

    public function handle(GmailImporter $importer): int
    {
        $users = User::whereNotNull('google_refresh_token')->get()->filter->hasGmail();

        foreach ($users as $user) {
            $stored = $importer->tryImport($user);

            $stored === null
                ? $this->warn("{$user->email}: {$user->gmail_sync_error}")
                : $this->info("{$user->email}: {$stored} mail(s) imported");
        }

        return self::SUCCESS;
    }
}
