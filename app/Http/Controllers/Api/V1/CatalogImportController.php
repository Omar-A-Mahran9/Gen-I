<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportStatus;
use App\Http\Requests\Business\CatalogImportRequest;
use App\Http\Requests\Business\CatalogImportTemplateRequest;
use App\Services\Business\CatalogImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Catalog import API (docs/catalog-import-contract.md).
 */
class CatalogImportController extends ApiController
{
    public function __construct(protected CatalogImportService $service) {}

    /**
     * 200 {success: true, data: report, message} when committed, partially committed or validated (dry run);
     * 422 {success: false, code: "import_rejected", data: report, message} when the batch is quarantined.
     */
    public function store(CatalogImportRequest $request): JsonResponse
    {
        $report = $this->service->import($request->file('file'), (int) $request->organizationId(), $request->user(), $request->mode(), $request->dryRun());
        $message = __('admin.catalog_import.messages.'.$report->status->value);

        if ($report->status === ImportStatus::Quarantined) {
            return $this->failure($message, Response::HTTP_UNPROCESSABLE_ENTITY, 'import_rejected', $report->toArray());
        }

        return $this->success($message, $report->toArray());
    }

    public function template(CatalogImportTemplateRequest $request): Response
    {
        return response($this->service->templateCsv($request->category()), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="catalog-import-template.csv"',
        ]);
    }
}
