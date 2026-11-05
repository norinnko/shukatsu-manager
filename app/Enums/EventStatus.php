<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventStatus: string
{
    use HasOptions;

    case Upcoming = 'upcoming';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => '予定',
            self::Done => '完了',
            self::Cancelled => 'キャンセル',
        };
    }
}
