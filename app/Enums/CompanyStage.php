<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a company is in the selection pipeline. Order of cases is the
 * order of the board columns.
 */
enum CompanyStage: string
{
    use HasOptions;

    case Interested = 'interested';
    case Waiting = 'waiting';
    case Seminar = 'seminar';
    case Es = 'es';
    case Applied = 'applied';
    case Webtest = 'webtest';
    case Interview = 'interview';
    case Final = 'final';
    case Joined = 'joined';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Interested => '気になる',
            self::Waiting => '案内待ち',
            self::Seminar => '説明会',
            self::Es => 'ES・エントリー',
            self::Applied => '応募済・結果待ち',
            self::Webtest => 'Webテスト',
            self::Interview => '面接',
            self::Final => '最終面接',
            self::Joined => 'インターン参加済',
            self::Offer => '内定',
            self::Rejected => '不合格',
            self::Declined => '辞退',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Interested, self::Waiting => 'slate',
            self::Seminar => 'violet',
            self::Es, self::Applied => 'sky',
            self::Webtest => 'orange',
            self::Interview, self::Final => 'emerald',
            self::Joined => 'teal',
            self::Offer => 'amber',
            self::Rejected, self::Declined => 'zinc',
        };
    }

    /**
     * Stages that mean the selection is over.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Rejected, self::Declined], true);
    }

    /**
     * Stages where we are waiting on the company.
     */
    public function isAwaitingResult(): bool
    {
        return $this === self::Applied;
    }
}
