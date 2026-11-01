<?php

namespace App\Enums;

/**
 * S2 attribute_defs.value_type: text, number or boolean.
 */
enum AttributeValueType: string
{
    use HasLabels;

    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';

    public static function translationKey(): string
    {
        return 'attribute_value_type';
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Number => 'info',
            default => 'secondary',
        };
    }
}
