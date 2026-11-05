<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums that are shown in the UI.
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * @return list<array{value: string, label: string, color: string|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => method_exists($case, 'color') ? $case->color() : null,
        ], self::cases());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
