<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Serialize dates with the app's UTC offset (e.g. 2026-10-09T14:00:00+09:00)
 * instead of converting them to UTC, so all-day dates never shift a day.
 */
trait SerializesLocalDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DateTimeInterface::ATOM);
    }
}
