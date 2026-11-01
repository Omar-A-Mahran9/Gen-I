<?php

namespace App\Enums;

/**
 * S2 organizations.status: active or suspended.
 */
enum OrganizationStatus: string
{
    use HasLabels;

    case Active = 'active';
    case Suspended = 'suspended';

    public static function translationKey(): string
    {
        return 'organization_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'danger',
            default => 'secondary',
        };
    }
}
