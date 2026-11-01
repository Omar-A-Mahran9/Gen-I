<?php

namespace App\Enums;

/**
 * S2 media_assets.classification: public, internal or restricted.
 */
enum MediaClassification: string
{
    use HasLabels;

    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';

    public static function translationKey(): string
    {
        return 'media_classification';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Restricted => 'danger',
            default => 'secondary',
        };
    }
}
