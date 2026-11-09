<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MovementReason;
use App\Http\Requests\Business\InventoryAdjustmentRequest;
use App\Http\Resources\Api\V1\InventoryBalanceResource;
use App\Http\Resources\Api\V1\InventoryMovementResource;
use App\Services\Business\InventoryService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InventoryController extends ApiController
{
    public function __construct(protected InventoryService $service) {}

    public function balances(Request $request): JsonResponse
    {
        Gate::authorize('inventory.view');
        $query = $this->service->balanceQuery($request->user(), ApiListing::filters($request, ['branch_id', 'variant_id', 'organization_id', 'stock']));

        return $this->successWithPagination(__('api.messages.retrieved'), InventoryBalanceResource::collection(ApiListing::paginate($query, $request, ['on_hand' => 'on_hand', 'updated_at' => 'source_updated_at'], '-source_updated_at')));
    }

    public function movements(Request $request): JsonResponse
    {
        Gate::authorize('inventory.view');
        $query = $this->service->movementQuery($request->user(), ApiListing::filters($request, ['branch_id', 'variant_id', 'reason', 'organization_id', 'from', 'to']));

        return $this->successWithPagination(__('api.messages.retrieved'), InventoryMovementResource::collection(ApiListing::paginate($query, $request, ['created_at' => 'id'], '-created_at')));
    }

    /**
     * Manual receipt/adjustment. Send an Idempotency-Key header to make retries safe:
     * a replay with the same key and payload returns the original movement (200).
     */
    public function adjust(InventoryAdjustmentRequest $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        abort_if($key !== null && ! preg_match('/^[A-Za-z0-9_\-:.]{8,100}$/', $key), 400, 'Invalid Idempotency-Key');

        $movement = $this->service->adjust(
            $request->branch(),
            $request->variant(),
            $request->integer('quantity'),
            MovementReason::from($request->validated('reason')),
            $request->validated('note'),
            $request->user(),
            $key,
        );

        return $this->success(
            __($movement->wasRecentlyCreated ? 'api.messages.stock_adjusted' : 'api.messages.idempotent_replay'),
            new InventoryMovementResource($movement->load(['branch', 'variant'])),
            $movement->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
