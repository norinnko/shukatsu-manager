<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventType: string
{
    use HasOptions;

    case Deadline = 'deadline';
    case Intern = 'intern';
    case Interview = 'interview';
    case Seminar = 'seminar';
    case Test = 'test';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Deadline => '締切',
            self::Intern => 'インターン',
            self::Interview => '面接',
            self::Seminar => '説明会',
            self::Test => 'Webテスト',
            self::Other => 'その他',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Deadline => 'red',
            self::Intern => 'blue',
            self::Interview => 'green',
            self::Seminar => 'violet',
            self::Test => 'orange',
            self::Other => 'slate',
        };
    }

    /**
     * Google Calendar event colorId (1-11) matching the in-app colour.
     */
    public function googleColorId(): string
    {
        return match ($this) {
            self::Deadline => '11', // Tomato
            self::Intern => '9',    // Blueberry
            self::Interview => '10', // Basil
            self::Seminar => '3',   // Grape
            self::Test => '6',      // Tangerine
            self::Other => '8',     // Graphite
        };
    }
}
