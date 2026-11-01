<?php

namespace App\Enums;

/**
 * S2 active/inactive status (branches, categories, brands, price lists, company members).
 */
enum ActiveStatus: string
{
    use HasLabels;

    case Active = 'active';
    case Inactive = 'inactive';

    public static function translationKey(): string
    {
        return 'active_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
            default => 'secondary',
        };
    }
}
