<?php

namespace App\Http\Requests\Business;

use App\Enums\ImportMode;
use App\Http\Requests\OrganizationScopedRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * Catalog import upload (docs/catalog-import-contract.md §2). File-level checks only;
 * the CSV content is validated row by row by CatalogImportService.
 */
class CatalogImportRequest extends OrganizationScopedRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimes:csv,txt', 'max:'.(int) config('admin.catalog_import.max_kb', 5120)],
            'mode' => ['nullable', Rule::enum(ImportMode::class)],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    public function mode(): ImportMode
    {
        return ImportMode::tryFrom((string) $this->validated('mode')) ?? ImportMode::Atomic;
    }

    public function dryRun(): bool
    {
        return $this->boolean('dry_run');
    }
}
