<?php

namespace App\Services\Business;

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use App\Exceptions\AdminActionException;
use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rules\TypedAttributeValue;
use App\Services\Audit\AuditLogger;
use App\Services\Business\Import\CatalogImportContract;
use App\Services\Business\Import\CsvReader;
use App\Services\Business\Import\ImportReport;
use App\Services\Business\Import\ImportRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Catalog import (UC19 "validate required fields, types, units and source"; "invalid batch
 * quarantined") following docs/catalog-import-contract.md.
 *
 * Pipeline: read CSV → check header → validate each row → cross-row checks (duplicate SKUs,
 * conflicting product data, SKUs owned by another product) → persist through ProductService
 * inside transactions → report. Categories, brands and attribute definitions are referenced,
 * never created; prices, stock and statuses are never touched.
 */
class CatalogImportService
{
    /** @var array<int, Collection<string, AttributeDefinition>> category id => applicable definitions keyed by code */
    protected array $applicableAttributes = [];

    public function __construct(
        protected CsvReader $reader,
        protected ProductService $products,
        protected AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException when the file as a whole is unusable (422, details.file)
     */
    public function import(UploadedFile $file, int $organizationId, User $actor, ImportMode $mode = ImportMode::Atomic, bool $dryRun = false): ImportReport
    {
        $this->applicableAttributes = [];
        $parsed = $this->reader->read((string) $file->getRealPath());
        $headers = $this->checkHeaders($parsed['headers'], $organizationId);
        $this->checkRowCount(count($parsed['rows']));

        $report = new ImportReport($mode, $dryRun, $file->getClientOriginalName(), (string) hash_file('sha256', (string) $file->getRealPath()));
        $report->totalRows = count($parsed['rows']);

        $candidates = $this->validateRows($parsed['rows'], $headers, $organizationId, $report);
        $candidates = $this->rejectDuplicateSkus($candidates, $report);
        $candidates = $this->rejectConflictingProducts($candidates, $report);
        $candidates = $this->rejectForeignSkus($candidates, $organizationId, $report);

        if ($dryRun) {
            foreach (array_keys($candidates) as $row) {
                $report->setOutcome($row, ImportReport::OUTCOME_VALID);
            }
            $report->status = ImportStatus::Validated;

            return $report;
        }

        if ($mode === ImportMode::Atomic) {
            $this->persistAtomically($candidates, $organizationId, $actor, $report);
        } else {
            $this->persistPartially($candidates, $organizationId, $actor, $report);
        }

        $this->audit->log('catalog.imported', null, [
            'organization_id' => $organizationId,
            'file' => $report->fileName,
            'sha256' => $report->sha256,
            'status' => $report->status->value,
            'mode' => $mode->value,
        ] + $report->toArray()['summary'], $actor);

        return $report;
    }

    /**
     * Header row of a blank template, optionally with the specification columns of a category.
     *
     * @return array<int, string>
     */
    public function templateColumns(?Category $category = null): array
    {
        $columns = CatalogImportContract::columns();

        if ($category !== null) {
            foreach ($this->attributesFor($category) as $code => $definition) {
                $columns[] = CatalogImportContract::SPEC_PREFIX.$code;
            }
        }

        return $columns;
    }

    /**
     * Blank CSV template (UTF-8 with BOM so spreadsheet applications keep Arabic text intact).
     */
    public function templateCsv(?Category $category = null): string
    {
        return "\xEF\xBB\xBF".implode(',', $this->templateColumns($category))."\r\n";
    }

    /**
     * Validate the header row and return the canonical column names.
     *
     * @param  array<int, string>  $rawHeaders
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    protected function checkHeaders(array $rawHeaders, int $organizationId): array
    {
        $headers = array_map(CatalogImportContract::normalizeHeader(...), $rawHeaders);
        $errors = [];

        if ($headers === [] || $headers === ['']) {
            throw ValidationException::withMessages(['file' => [__('admin.catalog_import.errors.missing_header')]]);
        }

        if (count($headers) === 1 && str_contains($headers[0], ';')) {
            throw ValidationException::withMessages(['file' => [__('admin.catalog_import.errors.wrong_delimiter')]]);
        }

        $duplicates = array_keys(array_filter(array_count_values($headers), fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            $errors[] = __('admin.catalog_import.errors.duplicate_columns', ['columns' => implode(', ', $duplicates)]);
        }

        if (in_array('', $headers, true)) {
            $errors[] = __('admin.catalog_import.errors.blank_column');
        }

        $missing = array_diff(CatalogImportContract::REQUIRED_COLUMNS, $headers);
        if ($missing !== []) {
            $errors[] = __('admin.catalog_import.errors.missing_columns', ['columns' => implode(', ', $missing)]);
        }

        $specColumns = array_filter($headers, CatalogImportContract::isSpecColumn(...));
        $unknown = array_diff($headers, CatalogImportContract::columns(), $specColumns, ['']);
        if ($unknown !== []) {
            $errors[] = __('admin.catalog_import.errors.unknown_columns', ['columns' => implode(', ', $unknown)]);
        }

        $specCodes = array_map(CatalogImportContract::specCode(...), $specColumns);
        if ($specCodes !== []) {
            $known = AttributeDefinition::query()->where('organization_id', $organizationId)->whereIn('code', $specCodes)->distinct()->pluck('code')->all();
            $unknownSpecs = array_diff($specCodes, $known);

            if ($unknownSpecs !== []) {
                $errors[] = __('admin.catalog_import.errors.unknown_attributes', ['codes' => implode(', ', $unknownSpecs)]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => array_values($errors)]);
        }

        return $headers;
    }

    /**
     * @throws ValidationException
     */
    protected function checkRowCount(int $rows): void
    {
        $max = (int) config('admin.catalog_import.max_rows', 5000);

        if ($rows === 0) {
            throw ValidationException::withMessages(['file' => [__('admin.catalog_import.errors.no_rows')]]);
        }

        if ($rows > $max) {
            throw ValidationException::withMessages(['file' => [__('admin.catalog_import.errors.too_many_rows', ['max' => $max, 'rows' => $rows])]]);
        }
    }

    /**
     * Field-level validation and reference resolution for every row.
     *
     * @param  array<int, array<int, string|null>>  $rows
     * @param  array<int, string>  $headers
     * @return array<int, array<string, mixed>> valid rows keyed by record number
     */
    protected function validateRows(array $rows, array $headers, int $organizationId, ImportReport $report): array
    {
        $categories = Category::query()->where('organization_id', $organizationId)->get()->keyBy('code');
        $brands = Brand::query()->where('organization_id', $organizationId)->get()->keyBy('name_normalized');
        $attributeNames = collect(CatalogImportContract::columns())->mapWithKeys(fn (string $column): array => [$column => $column])->all();
        $candidates = [];

        foreach ($rows as $line => $cells) {
            if (count($cells) !== count($headers)) {
                $report->addError($line, null, 'malformed_row', __('admin.catalog_import.errors.malformed_row', ['expected' => count($headers), 'actual' => count($cells)]));

                continue;
            }

            $row = CatalogImportContract::normalizeRow(array_combine($headers, $cells));
            $validator = Validator::make($row, CatalogImportContract::rowRules(), [], $attributeNames);

            if ($validator->fails()) {
                foreach ($validator->failed() as $column => $rules) {
                    $report->addError($line, $column, CatalogImportContract::errorCodeForRule((string) array_key_first($rules)), $validator->errors()->first($column), $row[$column] ?? null);
                }
            }

            $category = $row['category_code'] !== null ? $categories->get($row['category_code']) : null;
            if ($row['category_code'] !== null && $category === null && ! $validator->errors()->has('category_code')) {
                $report->addError($line, 'category_code', 'unknown_category', __('admin.catalog_import.errors.unknown_category'), $row['category_code']);
            }

            $brand = $row['brand_name'] !== null ? $brands->get(Brand::normalize($row['brand_name'])) : null;
            if ($row['brand_name'] !== null && $brand === null && ! $validator->errors()->has('brand_name')) {
                $report->addError($line, 'brand_name', 'unknown_brand', __('admin.catalog_import.errors.unknown_brand'), $row['brand_name']);
            }

            $specs = $category !== null ? $this->validateSpecifications($line, $row, $category, $report) : [];

            if ($report->hasFailed($line)) {
                continue;
            }

            $candidates[$line] = [
                'row' => $row,
                'category' => $category,
                'brand' => $brand,
                'product_key' => $brand->id.'|'.mb_strtolower($row['model_code']),
                'specs' => $specs,
            ];
        }

        return $candidates;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<int, array{definition: AttributeDefinition, value: string, source_reference: string, verified_at: string|null}>
     */
    protected function validateSpecifications(int $line, array $row, Category $category, ImportReport $report): array
    {
        $applicable = $this->attributesFor($category);
        $specs = [];

        foreach ($row as $column => $value) {
            if (! CatalogImportContract::isSpecColumn($column) || $value === null) {
                continue;
            }

            $definition = $applicable->get(CatalogImportContract::specCode($column));

            if ($definition === null) {
                $report->addError($line, $column, 'attribute_not_applicable', __('admin.catalog_import.errors.attribute_not_applicable', ['category' => $category->code]), $value);

                continue;
            }

            if (! TypedAttributeValue::passes($definition->value_type, $value)) {
                $report->addError($line, $column, 'invalid_type', __('admin.errors.attribute_value_type', ['attribute' => $definition->displayLabel(), 'type' => $definition->value_type->label()]), $value);

                continue;
            }

            $specs[] = ['definition' => $definition, 'value' => $value, 'source_reference' => (string) $row['spec_source_reference'], 'verified_at' => $row['spec_verified_at'] ?? null];
        }

        if ($specs !== [] && $row['spec_source_reference'] === null) {
            $report->addError($line, 'spec_source_reference', 'required', __('admin.catalog_import.errors.spec_source_required'));
        }

        return $specs;
    }

    /**
     * Attribute definitions applicable to a category (its own and its ancestors'), keyed by code;
     * the nearest category wins when an ancestor defines the same code.
     *
     * @return Collection<string, AttributeDefinition>
     */
    protected function attributesFor(Category $category): Collection
    {
        if (! isset($this->applicableAttributes[$category->id])) {
            $lineage = $category->lineageIds();
            $definitions = AttributeDefinition::query()->whereIn('category_id', $lineage)->orderBy('comparison_order')->orderBy('code')->get();

            $this->applicableAttributes[$category->id] = $definitions
                ->sortBy(fn (AttributeDefinition $definition): int => (int) array_search($definition->category_id, $lineage, true))
                ->unique('code')
                ->sortBy([['comparison_order', 'asc'], ['code', 'asc']])
                ->keyBy('code');
        }

        return $this->applicableAttributes[$category->id];
    }

    /**
     * A SKU may appear only once per file: every occurrence of a repeated SKU is rejected,
     * because the import cannot know which row is authoritative.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function rejectDuplicateSkus(array $candidates, ImportReport $report): array
    {
        $bySku = [];
        foreach ($candidates as $line => $candidate) {
            $bySku[$candidate['row']['sku']][] = $line;
        }

        foreach ($bySku as $sku => $lines) {
            if (count($lines) < 2) {
                continue;
            }

            foreach ($lines as $line) {
                $others = implode(', ', array_diff($lines, [$line]));
                $report->addError($line, 'sku', 'duplicate_in_file', __('admin.catalog_import.errors.duplicate_sku', ['rows' => $others]), (string) $sku);
                $report->markDuplicate($line);
                unset($candidates[$line]);
            }
        }

        return $candidates;
    }

    /**
     * Rows of the same product (brand + model code) must agree on product-level values.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function rejectConflictingProducts(array $candidates, ImportReport $report): array
    {
        $groups = [];
        foreach ($candidates as $line => $candidate) {
            $groups[$candidate['product_key']][] = $line;
        }

        foreach ($groups as $lines) {
            foreach (CatalogImportContract::PRODUCT_COLUMNS as $column) {
                $values = collect($lines)->map(fn (int $line) => $candidates[$line]['row'][$column] ?? null)->filter(fn ($value) => $value !== null)->unique();

                if ($values->count() < 2) {
                    continue;
                }

                foreach ($lines as $line) {
                    $report->addError($line, $column, 'conflicting_product_data', __('admin.catalog_import.errors.conflicting_product_data', ['rows' => implode(', ', $lines)]), $candidates[$line]['row'][$column] ?? null);
                    unset($candidates[$line]);
                }

                break;
            }
        }

        return $candidates;
    }

    /**
     * An existing SKU may only be updated through the product it already belongs to (BR02):
     * a file can never move a variant, with its prices and stock, to another product.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function rejectForeignSkus(array $candidates, int $organizationId, ImportReport $report): array
    {
        $skus = array_map(fn (array $candidate): string => $candidate['row']['sku'], $candidates);
        $existing = collect();

        foreach (array_chunk(array_values($skus), 500) as $chunk) {
            $existing = $existing->merge(ProductVariant::query()->where('organization_id', $organizationId)->whereIn('sku', $chunk)->with('product:id,brand_id,model_code')->get());
        }
        $existing = $existing->keyBy('sku');

        foreach ($candidates as $line => $candidate) {
            $variant = $existing->get($candidate['row']['sku']);

            if ($variant === null) {
                continue;
            }

            $ownerKey = $variant->product->brand_id.'|'.mb_strtolower($variant->product->model_code);
            if ($ownerKey !== $candidate['product_key']) {
                $report->addError($line, 'sku', 'sku_belongs_to_other_product', __('admin.catalog_import.errors.sku_other_product', ['model' => $variant->product->model_code]), $candidate['row']['sku']);
                unset($candidates[$line]);
            }
        }

        return $candidates;
    }

    /**
     * All rows or nothing: any invalid row, or any failure while writing, quarantines the batch
     * and rolls every write back.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    protected function persistAtomically(array $candidates, int $organizationId, User $actor, ImportReport $report): void
    {
        if ($report->hasErrors()) {
            $report->status = ImportStatus::Quarantined;

            return;
        }

        $outcomes = [];
        $counters = ['products_created' => 0, 'products_updated' => 0, 'specifications' => 0];
        $products = [];

        try {
            DB::transaction(function () use ($candidates, $organizationId, $actor, $report, &$outcomes, &$counters, &$products): void {
                foreach ($candidates as $line => $candidate) {
                    try {
                        $outcomes[$line] = $this->persistRow($candidate, $organizationId, $actor, $products, $counters);
                    } catch (AdminActionException|QueryException $e) {
                        $this->recordPersistenceError($line, $e, $report);

                        throw new ImportRolledBack;
                    }
                }
            });
        } catch (ImportRolledBack) {
            $report->status = ImportStatus::Quarantined;

            return;
        }

        foreach ($outcomes as $line => $outcome) {
            $report->setOutcome($line, $outcome);
        }
        $report->productsCreated = $counters['products_created'];
        $report->productsUpdated = $counters['products_updated'];
        $report->specificationsWritten = $counters['specifications'];
        $report->status = ImportStatus::Committed;
    }

    /**
     * Valid rows are committed one by one (each in its own transaction); invalid rows are skipped.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    protected function persistPartially(array $candidates, int $organizationId, User $actor, ImportReport $report): void
    {
        $products = [];

        foreach ($candidates as $line => $candidate) {
            $counters = ['products_created' => 0, 'products_updated' => 0, 'specifications' => 0];
            $known = $products;

            try {
                $outcome = DB::transaction(function () use ($candidate, $organizationId, $actor, &$products, &$counters): string {
                    return $this->persistRow($candidate, $organizationId, $actor, $products, $counters);
                });
            } catch (AdminActionException|QueryException $e) {
                $products = $known;
                $this->recordPersistenceError($line, $e, $report);

                continue;
            }

            $report->setOutcome($line, $outcome);
            $report->productsCreated += $counters['products_created'];
            $report->productsUpdated += $counters['products_updated'];
            $report->specificationsWritten += $counters['specifications'];
        }

        $report->status = match (true) {
            ! $report->hasErrors() => ImportStatus::Committed,
            $report->successfulRows() > 0 => ImportStatus::PartiallyCommitted,
            default => ImportStatus::Quarantined,
        };
    }

    /**
     * Create or update the product, then the exact variant, then merge its specifications.
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, Product>  $products  product cache keyed by product key
     * @param  array{products_created: int, products_updated: int, specifications: int}  $counters
     */
    protected function persistRow(array $candidate, int $organizationId, User $actor, array &$products, array &$counters): string
    {
        $row = $candidate['row'];
        $productData = array_filter([
            'category_id' => $candidate['category']->id,
            'name_en' => $row['product_name_en'],
            'name_ar' => $row['product_name_ar'],
            'description' => $row['product_description'],
            'source_reference' => $row['product_source_reference'],
        ], fn ($value) => $value !== null);

        $product = $products[$candidate['product_key']] ?? $this->findProduct($organizationId, $candidate['brand']->id, $row['model_code']);
        $productChanged = false;

        if ($product === null) {
            $product = $this->products->create($productData + [
                'organization_id' => $organizationId,
                'brand_id' => $candidate['brand']->id,
                'model_code' => $row['model_code'],
            ], $actor);
            $counters['products_created']++;
            $productChanged = true;
        } elseif (! isset($products[$candidate['product_key']])) {
            $product = $this->products->update($product, $productData, $actor);
            if ($product->wasChanged()) {
                $counters['products_updated']++;
                $productChanged = true;
            }
        }
        $products[$candidate['product_key']] = $product;

        $variant = ProductVariant::query()->where('organization_id', $organizationId)->where('sku', $row['sku'])->first();
        $isNewVariant = $variant === null;
        $variant = $this->products->saveVariant($product, [
            'sku' => $row['sku'],
            'variant_name' => $row['variant_name'],
            'item_kind' => $row['item_kind'],
            'condition' => $row['condition'],
        ], $actor, $variant);

        $written = $candidate['specs'] !== [] ? $this->products->mergeVariantAttributes($variant, $candidate['specs'], $actor) : 0;
        $counters['specifications'] += $written;

        return match (true) {
            $isNewVariant => ImportReport::OUTCOME_CREATED,
            $productChanged || $variant->wasChanged() || $written > 0 => ImportReport::OUTCOME_UPDATED,
            default => ImportReport::OUTCOME_UNCHANGED,
        };
    }

    /**
     * Product identity is (organization, brand, model code); model codes match case-insensitively
     * on every database engine, as MySQL's unique index does.
     */
    protected function findProduct(int $organizationId, int $brandId, string $modelCode): ?Product
    {
        $id = Product::query()
            ->where('organization_id', $organizationId)
            ->where('brand_id', $brandId)
            ->get(['id', 'model_code'])
            ->first(fn (Product $product): bool => mb_strtolower($product->model_code) === mb_strtolower($modelCode))
            ?->id;

        return $id !== null ? Product::query()->find($id) : null;
    }

    protected function recordPersistenceError(int $line, Throwable $e, ImportReport $report): void
    {
        if ($e instanceof AdminActionException) {
            $report->addError($line, null, 'business_rule_violation', $e->getMessage());

            return;
        }

        report($e);
        $report->addError($line, null, 'conflict', __('admin.catalog_import.errors.write_conflict'));
    }
}
