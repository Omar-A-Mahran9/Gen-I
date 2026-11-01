<?php

namespace App\Enums;

/**
 * S2 product_variants.status: active, unavailable or retired.
 */
enum VariantStatus: string
{
    use HasLabels;

    case Active = 'active';
    case Unavailable = 'unavailable';
    case Retired = 'retired';

    public static function translationKey(): string
    {
        return 'variant_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Unavailable => 'warning',
            self::Retired => 'danger',
            default => 'secondary',
        };
    }
}
