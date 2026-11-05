<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CompanyPhase: string
{
    use HasOptions;

    case Intern = 'intern';
    case Early = 'early';
    case Main = 'main';

    public function label(): string
    {
        return match ($this) {
            self::Intern => 'インターン',
            self::Early => '早期選考',
            self::Main => '本選考',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Intern => 'sky',
            self::Early => 'rose',
            self::Main => 'indigo',
        };
    }
}
