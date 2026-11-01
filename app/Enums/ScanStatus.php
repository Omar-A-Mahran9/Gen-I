<?php

namespace App\Enums;

/**
 * S2 media_assets.scan_status: pending, clean or blocked.
 */
enum ScanStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Clean = 'clean';
    case Blocked = 'blocked';

    public static function translationKey(): string
    {
        return 'scan_status';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Clean => 'success',
            self::Blocked => 'danger',
            default => 'secondary',
        };
    }
}
