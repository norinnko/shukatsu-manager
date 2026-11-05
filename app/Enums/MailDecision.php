<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Whether a mail should be shown, judged against the user's rules.
 */
enum MailDecision: string
{
    use HasOptions;

    case Keep = 'keep';
    case Unsure = 'unsure';
    case Exclude = 'exclude';

    public function label(): string
    {
        return match ($this) {
            self::Keep => '表示',
            self::Unsure => '念のため表示',
            self::Exclude => '除外',
        };
    }
}
