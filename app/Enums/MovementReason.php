<?php

namespace App\Enums;

/**
 * S2 inventory_movements.reason: receipt, sale, return, adjustment or consumption.
 */
enum MovementReason: string
{
    use HasLabels;

    case Receipt = 'receipt';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Consumption = 'consumption';

    /**
     * Reasons staff may record manually. Sale, return and consumption are
     * written only by the order/return/repair modules (not yet enabled).
     *
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [self::Receipt, self::Adjustment];
    }

    public static function translationKey(): string
    {
        return 'movement_reason';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Sale => 'primary',
            self::Return => 'info',
            self::Adjustment => 'warning',
            self::Consumption => 'danger',
            default => 'secondary',
        };
    }
}
