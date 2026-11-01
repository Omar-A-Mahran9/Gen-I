<?php

namespace App\Enums;

/**
 * S2 companies.status: pending, verified or suspended.
 */
enum CompanyStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';

    public static function translationKey(): string
    {
        return 'company_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Suspended => 'danger',
            default => 'secondary',
        };
    }
}
