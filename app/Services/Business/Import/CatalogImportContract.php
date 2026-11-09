<?php

namespace App\Services\Business\Import;

use App\Enums\ItemCondition;
use App\Enums\ItemKind;
use App\Rules\CategoryCode;
use App\Rules\Sku;
use Illuminate\Validation\Rule;

/**
 * Catalog import contract, version 1 (docs/catalog-import-contract.md).
 *
 * One CSV row describes one exact saleable variant (BR02) together with the product
 * (model identity) it belongs to. Field names, types and lengths follow the S2 §9.3 data
 * dictionary as built (products, product_variants, variant_attributes). Prices, stock,
 * statuses, warranty policies and media are deliberately not part of this contract.
 */
class CatalogImportContract
{
    public const VERSION = '1';

    /** Prefix of a specification column: "spec:<attribute_code>". */
    public const SPEC_PREFIX = 'spec:';

    /**
     * Columns every file must contain.
     *
     * @var array<int, string>
     */
    public const REQUIRED_COLUMNS = [
        'category_code',
        'brand_name',
        'model_code',
        'product_name_en',
        'product_source_reference',
        'sku',
        'variant_name',
        'item_kind',
        'condition',
    ];

    /**
     * Columns a file may contain.
     *
     * @var array<int, string>
     */
    public const OPTIONAL_COLUMNS = [
        'product_name_ar',
        'product_description',
        'spec_source_reference',
        'spec_verified_at',
    ];

    /**
     * Product-level columns: every row of the same product (brand + model code) must agree on
     * their non-empty values, so variants never carry contradictory product data.
     *
     * @var array<int, string>
     */
    public const PRODUCT_COLUMNS = [
        'category_code',
        'product_name_en',
        'product_name_ar',
        'product_description',
        'product_source_reference',
    ];

    /**
     * Enum columns normalised to lower case before validation.
     *
     * @var array<int, string>
     */
    public const LOWERCASE_COLUMNS = ['item_kind', 'condition'];

    /**
     * @return array<int, string>
     */
    public static function columns(): array
    {
        return array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);
    }

    public static function isSpecColumn(string $column): bool
    {
        return str_starts_with($column, self::SPEC_PREFIX);
    }

    public static function specCode(string $column): string
    {
        return substr($column, strlen(self::SPEC_PREFIX));
    }

    /**
     * Header cell → canonical column name (trimmed, lower case, BOM removed, spaces around ":" removed).
     */
    public static function normalizeHeader(string $header): string
    {
        $header = strtolower(trim(str_replace("\u{FEFF}", '', $header)));

        return (string) preg_replace('/\s*:\s*/', ':', $header);
    }

    /**
     * Normalise a row before validation: trim every cell, turn empty cells into null, add absent
     * optional columns as null and
     * apply the same canonical forms the admin/API forms use (SKU upper case, category code
     * lower case, enum values lower case).
     *
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    public static function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $column => $value) {
            $value = $value === null ? null : trim($value);
            $normalized[$column] = $value === '' ? null : $value;
        }

        $normalized += array_fill_keys(self::columns(), null);

        if ($normalized['sku'] ?? null) {
            $normalized['sku'] = Sku::normalize($normalized['sku']);
        }

        if ($normalized['category_code'] ?? null) {
            $normalized['category_code'] = CategoryCode::normalize($normalized['category_code']);
        }

        foreach (self::LOWERCASE_COLUMNS as $column) {
            if ($normalized[$column] ?? null) {
                $normalized[$column] = strtolower($normalized[$column]);
            }
        }

        return $normalized;
    }

    /**
     * Row validation rules. Lengths and allowed values mirror ProductRequest / VariantRequest and
     * the database columns; specification values are validated against their attribute type separately.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rowRules(): array
    {
        return [
            'category_code' => ['required', 'string', new CategoryCode],
            'brand_name' => ['required', 'string', 'max:100'],
            'model_code' => ['required', 'string', 'max:100'],
            'product_name_en' => ['required', 'string', 'max:200'],
            'product_name_ar' => ['nullable', 'string', 'max:200'],
            'product_description' => ['nullable', 'string', 'max:10000'],
            'product_source_reference' => ['required', 'string', 'max:2000'],
            'sku' => ['required', 'string', new Sku],
            'variant_name' => ['required', 'string', 'max:160'],
            'item_kind' => ['required', Rule::enum(ItemKind::class)],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'spec_source_reference' => ['nullable', 'string', 'max:2000'],
            'spec_verified_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * Machine-readable error code for a failed Laravel validation rule.
     */
    public static function errorCodeForRule(string $rule): string
    {
        return match (true) {
            in_array($rule, ['Required', 'RequiredWith', 'RequiredIf'], true) => 'required',
            $rule === 'Max' => 'too_long',
            in_array($rule, ['Date', 'BeforeOrEqual'], true) => 'invalid_date',
            $rule === Sku::class, $rule === CategoryCode::class => 'invalid_format',
            default => 'invalid_value',
        };
    }
}
