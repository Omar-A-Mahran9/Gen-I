<?php

namespace App\Enums;

/**
 * S2 product_variants.condition: new, refurbished or used.
 */
enum ItemCondition: string
{
    use HasLabels;

    case New = 'new';
    case Refurbished = 'refurbished';
    case Used = 'used';

    public static function translationKey(): string
    {
        return 'item_condition';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Refurbished => 'info',
            self::Used => 'warning',
            default => 'secondary',
        };
    }
}
