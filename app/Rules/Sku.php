<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Exact variant SKU (S2 product_variants.sku varchar(80)): letters, digits, dot, underscore and hyphen.
 * Callers normalise to upper case before validating; uniqueness is checked separately (per organization).
 */
class Sku implements ValidationRule
{
    public const MAX_LENGTH = 80;

    public const PATTERN = '/^[A-Za-z0-9._\-]+$/';

    /**
     * Normalised form used for storage and duplicate detection.
     */
    public static function normalize(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            $fail(__('admin.errors.sku_format', ['max' => self::MAX_LENGTH]));
        }
    }
}
