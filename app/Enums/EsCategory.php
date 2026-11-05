<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EsCategory: string
{
    use HasOptions;

    case SelfPr = 'self_pr';
    case Gakuchika = 'gakuchika';
    case Motivation = 'motivation';
    case Skills = 'skills';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SelfPr => '自己PR',
            self::Gakuchika => 'ガクチカ',
            self::Motivation => '志望動機・参加理由',
            self::Skills => '技術・開発経験',
            self::Other => 'その他',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SelfPr => 'rose',
            self::Gakuchika => 'amber',
            self::Motivation => 'sky',
            self::Skills => 'emerald',
            self::Other => 'slate',
        };
    }
}
