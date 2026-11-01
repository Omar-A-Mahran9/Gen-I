<?php

namespace App\Enums;

/**
 * S2 policy_versions.status: draft, approved or retired (approved versions are immutable).
 */
enum PolicyStatus: string
{
    use HasLabels;

    case Draft = 'draft';
    case Approved = 'approved';
    case Retired = 'retired';

    public static function translationKey(): string
    {
        return 'policy_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Approved => 'success',
            self::Retired => 'danger',
            default => 'secondary',
        };
    }
}
