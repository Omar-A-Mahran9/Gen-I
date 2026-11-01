<?php

namespace App\Enums;

/**
 * S2 company_members.member_role: buyer, approver or viewer.
 */
enum MemberRole: string
{
    use HasLabels;

    case Buyer = 'buyer';
    case Approver = 'approver';
    case Viewer = 'viewer';

    public static function translationKey(): string
    {
        return 'member_role';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Approver => 'primary',
            self::Buyer => 'info',
            default => 'secondary',
        };
    }
}
