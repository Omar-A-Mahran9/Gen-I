<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Category code (S2 categories.code varchar(50)): lower-case letters, digits, underscore and hyphen.
 * Callers normalise to lower case before validating; uniqueness is checked separately (per organization).
 */
class CategoryCode implements ValidationRule
{
    public const MAX_LENGTH = 50;

    public const PATTERN = '/^[a-z0-9_\-]+$/';

    public static function normalize(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            $fail(__('admin.errors.category_code_format', ['max' => self::MAX_LENGTH]));
        }
    }
}
