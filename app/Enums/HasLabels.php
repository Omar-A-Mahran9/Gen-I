<?php

namespace App\Enums;

/**
 * Translated labels and option lists for backed string enums.
 * Labels live in lang/{ar,en}/admin.php under "enums.<key>.<value>".
 */
trait HasLabels
{
    abstract public static function translationKey(): string;

    public function label(): string
    {
        return __('admin.enums.'.static::translationKey().'.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function badgeColor(): string
    {
        return 'secondary';
    }
}
