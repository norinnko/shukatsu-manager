<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What kind of mail it is, as judged by OpenAI.
 */
enum MailCategory: string
{
    use HasOptions;

    case Schedule = 'schedule';
    case Result = 'result';
    case Task = 'task';
    case Info = 'info';
    case Ad = 'ad';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Schedule => '予定',
            self::Result => '選考結果',
            self::Task => '要対応',
            self::Info => '案内',
            self::Ad => '宣伝',
            self::Other => 'その他',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Schedule => 'green',
            self::Result => 'violet',
            self::Task => 'red',
            self::Info => 'blue',
            self::Ad => 'slate',
            self::Other => 'slate',
        };
    }

    /**
     * Interview dates, results and deadlines: never hidden automatically.
     */
    public function isImportant(): bool
    {
        return in_array($this, [self::Schedule, self::Result, self::Task], true);
    }
}
