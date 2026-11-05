<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmailStatus: string
{
    use HasOptions;

    case New = 'new';
    case Done = 'done';
    case Ignored = 'ignored';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::New => '未確認',
            self::Done => '確認済み',
            self::Ignored => '無視',
            self::Excluded => '自動で除外',
        };
    }
}
