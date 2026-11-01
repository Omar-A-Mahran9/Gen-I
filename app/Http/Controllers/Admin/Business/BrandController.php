<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\BrandRequest;
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
                'name' => '<a href="'.e(route('admin.brands.edit', $b)).'" class="text-gray-800 text-hover-primary fw-bold">'.e($b->name).'</a><div class="text-muted fs-7">'.e($b->organization?->name).'</div>',
                'products' => (int) $b->products_count,
                'status' => view('admin.business.partials.status-badge', ['status' => $b->status])->render(),
                'created_at' => e(format_date($b->created_at)),
                'actions' => view('admin.business.partials.row-actions', ['model' => $b, 'route' => 'brands', 'param' => $b, 'noShow' => true])->render(),
            ])
            ->toResponse();
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Brand::class);

        return view('admin.business.brands.form', ['brand' => new Brand, 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function store(BrandRequest $request): JsonResponse|RedirectResponse
    {
        $this->service->saveBrand($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.brands.index'));
    }

    public function show(Brand $brand): RedirectResponse
    {
        Gate::authorize('view', $brand);

        return redirect()->route('admin.brands.edit', $brand);
    }

    public function edit(Request $request, Brand $brand): View
    {
        Gate::authorize('view', $brand);

        return view('admin.business.brands.form', ['brand' => $brand->loadCount('products'), 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse|RedirectResponse
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
