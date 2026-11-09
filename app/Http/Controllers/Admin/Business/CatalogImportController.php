<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use App\Http\Requests\Business\CatalogImportRequest;
use App\Http\Requests\Business\CatalogImportTemplateRequest;
use App\Models\Product;
use App\Services\Business\CatalogImportService;
use App\Services\Business\Import\CatalogImportContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Catalog import page (UC19). Same service as POST /api/v1/catalog/imports.
 */
class CatalogImportController extends BusinessController
{
    public function __construct(protected CatalogImportService $service) {}

    public function create(Request $request): View
    {
        Gate::authorize('import', Product::class);

        return view('admin.business.catalog-import.create', [
            'product' => new Product,
            'organizations' => $this->organizationOptions($request->user()),
            'modes' => ImportMode::options(),
            'requiredColumns' => CatalogImportContract::REQUIRED_COLUMNS,
            'optionalColumns' => CatalogImportContract::OPTIONAL_COLUMNS,
            'report' => $request->session()->get('import_report'),
        ]);
    }

    /**
     * AJAX form (data-geni-ajax-form): validation errors show inline under the fields (422);
     * once processed, the report is flashed and the page reloads to render it. Without
     * JavaScript the same endpoint redirects with a flash message.
     */
    public function store(CatalogImportRequest $request): JsonResponse|RedirectResponse
    {
        $report = $this->service->import($request->file('file'), (int) $request->organizationId(), $request->user(), $request->mode(), $request->dryRun());
        $message = __('admin.catalog_import.messages.'.$report->status->value);
        $level = $report->status === ImportStatus::Quarantined ? 'error' : 'success';

        $request->session()->flash('import_report', $report->toArray());

        if (! $request->expectsJson()) {
            return redirect()->route('admin.catalog.import.create')->with($level, $message);
        }

        return $this->success($request, $message, route('admin.catalog.import.create'), ['level' => $level]);
    }

    public function template(CatalogImportTemplateRequest $request): Response
    {
        return response($this->service->templateCsv($request->category()), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="catalog-import-template.csv"',
        ]);
    }
}
