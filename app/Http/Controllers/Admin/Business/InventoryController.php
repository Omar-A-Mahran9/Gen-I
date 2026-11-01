<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\MovementReason;
use App\Http\Requests\Business\InventoryAdjustmentRequest;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Services\Business\InventoryService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends BusinessController
{
    public function __construct(protected InventoryService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('inventory.view');

        if (! $request->expectsJson()) {
            return view('admin.business.inventory.index', $this->filterOptions($request));
        }

        return DataTable::of($this->service->balanceQuery($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['on_hand' => 'on_hand', 'reserved' => 'reserved', 'updated_at' => 'source_updated_at'])
            ->defaultOrder('source_updated_at')
            ->map(fn (InventoryBalance $b) => [
                'branch' => '<span class="badge badge-light fw-bold" dir="ltr">'.e($b->branch?->code).'</span> '.e($b->branch?->name),
                'variant' => '<span class="fw-bold" dir="ltr">'.e($b->variant?->sku).'</span><div class="text-muted fs-7">'.e($b->variant?->product?->displayName()).' · '.e($b->variant?->variant_name).'</div>',
                'on_hand' => '<span class="fw-bold fs-6">'.(int) $b->on_hand.'</span>',
                'reserved' => (int) $b->reserved,
                'available' => '<span class="badge '.($b->available() > 0 ? 'badge-light-success' : 'badge-light-danger').' fs-7">'.$b->available().'</span>',
                'updated_at' => e(format_datetime($b->source_updated_at)),
                'actions' => $request->user()->can('inventory.adjust')
                    ? '<button type="button" class="btn btn-sm btn-light-primary" data-geni-adjust data-branch="'.(int) $b->branch_id.'" data-variant="'.(int) $b->variant_id.'" data-label="'.e($b->variant?->sku.' @ '.$b->branch?->code).'">'.e(__('admin.inventory.adjust')).'</button>'
                    : '',
            ])
            ->toResponse();
    }

    public function movements(Request $request): View|JsonResponse
    {
        Gate::authorize('inventory.view');

        if (! $request->expectsJson()) {
            return view('admin.business.inventory.movements', $this->filterOptions($request));
        }

        return DataTable::of($this->service->movementQuery($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['created_at' => 'id', 'quantity_delta' => 'quantity_delta'])
            ->defaultOrder('id')
            ->map(fn (InventoryMovement $m) => [
                'created_at' => e(format_datetime($m->created_at)),
                'branch' => '<span class="badge badge-light fw-bold" dir="ltr">'.e($m->branch?->code).'</span>',
                'variant' => '<span class="fw-bold" dir="ltr">'.e($m->variant?->sku).'</span>',
                'quantity_delta' => '<span class="fw-bold '.($m->quantity_delta > 0 ? 'text-success' : 'text-danger').'" dir="ltr">'.($m->quantity_delta > 0 ? '+' : '').(int) $m->quantity_delta.'</span>',
                'on_hand_after' => (int) $m->on_hand_after,
                'reason' => view('admin.business.partials.status-badge', ['status' => $m->reason])->render(),
                'note' => e($m->note ?? '—'),
                'author' => e($m->author?->name ?? __('admin.audit.system')),
            ])
            ->toResponse();
    }

    public function adjust(InventoryAdjustmentRequest $request, InventoryService $service): JsonResponse|RedirectResponse
    {
        $movement = $service->adjust(
            $request->branch(),
            $request->variant(),
            $request->integer('quantity'),
            MovementReason::from($request->validated('reason')),
            $request->validated('note'),
            $request->user(),
            $request->input('idempotency_key'),
        );

        return $this->success($request, __('admin.inventory.messages.adjusted', ['on_hand' => $movement->on_hand_after]), data: ['on_hand' => $movement->on_hand_after]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterOptions(Request $request): array
    {
        return [
            'branches' => Branch::query()->forUser($request->user())->orderBy('code')->get(['id', 'code', 'name', 'organization_id', 'status']),
            'variants' => ProductVariant::query()->forUser($request->user())->with('product:id,name_en,name_ar')->orderBy('sku')->get(['id', 'sku', 'variant_name', 'product_id', 'organization_id']),
            'reasons' => MovementReason::options(),
            'manualReasons' => collect(MovementReason::manual())->mapWithKeys(fn (MovementReason $r) => [$r->value => $r->label()])->all(),
        ];
    }
}
