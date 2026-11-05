<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CompanyPriority: string
{
    use HasOptions;

    case First = 'first';
    case Normal = 'normal';
    case Backup = 'backup';

    public function label(): string
    {
        return match ($this) {
            self::First => '◎ 第一志望',
            self::Normal => '○ 志望',
            self::Backup => '▼ 滑り止め',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::First => 'rose',
            self::Normal => 'sky',
            self::Backup => 'zinc',
        };
    }

    /**
     * Sort weight: lower comes first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::First => 0,
            self::Normal => 1,
            self::Backup => 2,
        };
    }
}
