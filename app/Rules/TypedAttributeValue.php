<?php

namespace App\Rules;

use App\Enums\AttributeValueType;
use App\Models\AttributeDefinition;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A specification value must match its attribute definition's value type (S2 variant_attributes:
 * "exactly one typed value; type … must match definition"). Limits follow the column types:
 * number_value decimal(18,6), text_value text (≤ 2000 characters by convention), boolean_value boolean.
 *
 * Empty values (null or "") pass: they mean "no value" and are handled by the caller.
 */
class TypedAttributeValue implements ValidationRule
{
    public const TEXT_MAX_LENGTH = 2000;

    /** Accepted boolean spellings (JSON booleans, 0/1 and true/false). */
    public const BOOLEAN_VALUES = [true, false, 0, 1, '0', '1', 'true', 'false'];

    public function __construct(protected AttributeDefinition $definition) {}

    public static function passes(AttributeValueType $type, mixed $value): bool
    {
        if (is_string($value)) {
            $value = $type === AttributeValueType::Text ? $value : strtolower(trim($value));
        }

        return match ($type) {
            AttributeValueType::Number => is_numeric($value) && abs((float) $value) < 1e12 && self::decimalPlaces((string) $value) <= 6,
            AttributeValueType::Boolean => in_array($value, self::BOOLEAN_VALUES, true),
            AttributeValueType::Text => is_string($value) && mb_strlen($value) <= self::TEXT_MAX_LENGTH,
        };
    }

    /**
     * Cast a validated value to the typed column it is stored in.
     *
     * @return array{text_value: string|null, number_value: string|null, boolean_value: bool|null}
     */
    public static function toColumns(AttributeValueType $type, mixed $value): array
    {
        $columns = ['text_value' => null, 'number_value' => null, 'boolean_value' => null];

        match ($type) {
            AttributeValueType::Number => $columns['number_value'] = trim((string) $value),
            AttributeValueType::Boolean => $columns['boolean_value'] = filter_var(is_string($value) ? strtolower(trim($value)) : $value, FILTER_VALIDATE_BOOLEAN),
            AttributeValueType::Text => $columns['text_value'] = (string) $value,
        };

        return $columns;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! self::passes($this->definition->value_type, $value)) {
            $fail(__('admin.errors.attribute_value_type', [
                'attribute' => $this->definition->displayLabel(),
                'type' => $this->definition->value_type->label(),
            ]));
        }
    }

    protected static function decimalPlaces(string $number): int
    {
        $number = strtolower(trim($number));

        if (str_contains($number, 'e')) {
            return 0;
        }

        $dot = strpos($number, '.');

        return $dot === false ? 0 : strlen(rtrim(substr($number, $dot + 1), '0'));
    }
}
