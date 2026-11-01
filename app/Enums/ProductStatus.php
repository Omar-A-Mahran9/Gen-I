<?php

namespace App\Enums;

/**
 * S2 products.status: draft, approved, published or retired.
 */
enum ProductStatus: string
{
    use HasLabels;

    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';
    case Retired = 'retired';

    public static function translationKey(): string
    {
        return 'product_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Approved => 'info',
            self::Published => 'success',
            self::Retired => 'danger',
            default => 'secondary',
        };
    }
}
