<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StoreBranchRequest;
use App\Http\Requests\Business\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\Business\BranchService;
use App\Services\Business\InventoryService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends BusinessController
{
    public function __construct(protected BranchService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Branch::class);

        if (! $request->expectsJson()) {
            return view('admin.business.branches.index', ['organizations' => $this->organizationOptions($request->user())]);
        }

        return DataTable::of($this->service->query($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['code' => 'code', 'name' => 'name', 'city' => 'city', 'status' => 'status'])
            ->defaultOrder('code', 'asc')
            ->map(fn (Branch $b) => [
                'code' => '<span class="badge badge-light fw-bold" dir="ltr">'.e($b->code).'</span>',
                'name' => '<a href="'.e(route('admin.branches.show', $b)).'" class="text-gray-800 text-hover-primary fw-bold">'.e($b->name).'</a><div class="text-muted fs-7">'.e($b->organization?->name).'</div>',
                'city' => e($b->city ?? '—'),
                'phone' => '<span dir="ltr">'.e($b->phone ?? '—').'</span>',
                'items' => (int) $b->inventory_balances_count,
                'status' => view('admin.business.partials.status-badge', ['status' => $b->status])->render(),
                'actions' => view('admin.business.partials.row-actions', ['model' => $b, 'route' => 'branches', 'param' => $b])->render(),
            ])
            ->toResponse();
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Branch::class);

        return view('admin.business.branches.form', ['branch' => new Branch(['opening_hours' => BranchService::normalizeOpeningHours([])]), 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function store(StoreBranchRequest $request): JsonResponse|RedirectResponse
    {
        $branch = $this->service->create($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.branches.show', $branch));
    }

    public function show(Request $request, Branch $branch, InventoryService $inventory): View
    {
        Gate::authorize('view', $branch);

        return view('admin.business.branches.show', [
            'branch' => $branch->load('organization'),
            'stock' => $request->user()->can('inventory.view')
                ? $inventory->balanceQuery($request->user(), ['branch_id' => $branch->id])->orderByDesc('source_updated_at')->limit(15)->get()
                : null,
            'stockTotals' => $branch->inventoryBalances()->selectRaw('COUNT(*) as items, COALESCE(SUM(on_hand), 0) as on_hand, COALESCE(SUM(reserved), 0) as reserved')->first(),
        ]);
    }

    public function edit(Request $request, Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return view('admin.business.branches.form', ['branch' => $branch, 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse|RedirectResponse
    {
        $this->service->update($branch, $request->payload(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.branches.show', $branch));
    }

    public function status(Request $request, Branch $branch): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $branch);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);
        $this->service->setStatus($branch, ActiveStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, Branch $branch): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $branch);
        $this->service->delete($branch, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.branches.index'));
    }
}
