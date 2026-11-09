<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StoreBrandRequest;
use App\Http\Requests\Business\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\Business\CatalogService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends BusinessController
{
    public function __construct(protected CatalogService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Brand::class);

        if (! $request->expectsJson()) {
            return view('admin.business.brands.index', ['organizations' => $this->organizationOptions($request->user())]);
        }

        return DataTable::of($this->service->brandQuery($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['name' => 'name', 'status' => 'status', 'created_at' => 'created_at'])
            ->defaultOrder('name', 'asc')
            ->map(fn (Brand $b) => [
                'name' => '<span class="text-gray-800 fw-bold">'.e($b->name).'</span><div class="text-muted fs-7">'.e($b->organization?->name).'</div>',
                'products' => (int) $b->products_count,
                'status' => view('admin.business.partials.status-badge', ['status' => $b->status])->render(),
                'created_at' => e(format_date($b->created_at)),
                'actions' => view('admin.business.partials.row-actions', [
                    'model' => $b, 'route' => 'brands', 'param' => $b, 'noShow' => true,
                    'editModal' => '#geni_brand_modal', 'record' => ['id' => $b->id, 'organization_id' => $b->organization_id, 'name' => $b->name],
                    'statusUrl' => route('admin.brands.status', $b),
                    'statusTarget' => $b->status === ActiveStatus::Active ? ActiveStatus::Inactive : ActiveStatus::Active,
                ])->render(),
            ])
            ->toResponse();
    }

    public function store(StoreBrandRequest $request): JsonResponse|RedirectResponse
    {
        $this->service->saveBrand($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.brands.index'));
    }

    public function show(Brand $brand): RedirectResponse
    {
        Gate::authorize('view', $brand);

        return redirect()->route('admin.brands.index');
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse|RedirectResponse
    {
        $this->service->saveBrand($request->payload(), $request->user(), $brand);

        return $this->success($request, __('admin.messages.updated'), route('admin.brands.index'));
    }

    public function status(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $brand);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);
        $this->service->setBrandStatus($brand, ActiveStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $brand);
        $this->service->deleteBrand($brand, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.brands.index'));
    }
}
