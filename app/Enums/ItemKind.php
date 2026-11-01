<?php

namespace App\Enums;

/**
 * S2 product_variants.item_kind: device, accessory or spare_part.
 */
enum ItemKind: string
{
    use HasLabels;

    case Device = 'device';
    case Accessory = 'accessory';
    case SparePart = 'spare_part';

    public static function translationKey(): string
    {
        return 'item_kind';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Device => 'primary',
            default => 'secondary',
        };
    }
}
