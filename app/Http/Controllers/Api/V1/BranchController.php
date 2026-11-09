<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Requests\Business\StoreBranchRequest;
use App\Http\Requests\Business\UpdateBranchRequest;
use App\Http\Resources\Api\V1\BranchResource;
use App\Http\Resources\Api\V1\InventoryBalanceResource;
use App\Models\Branch;
use App\Services\Business\BranchService;
use App\Services\Business\InventoryService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BranchController extends ApiController
{
    public function __construct(protected BranchService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Branch::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'city']));

        return $this->successWithPagination(__('api.messages.retrieved'), BranchResource::collection(ApiListing::paginate($query, $request, ['code' => 'code', 'name' => 'name', 'city' => 'city', 'created_at' => 'created_at'], 'code')));
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new BranchResource($this->service->create($request->payload(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(Branch $branch): JsonResponse
    {
        Gate::authorize('view', $branch);

        return $this->success(__('api.messages.retrieved'), new BranchResource($branch->loadCount('inventoryBalances')));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new BranchResource($this->service->update($branch, $request->payload(), $request->user())));
    }

    public function status(Request $request, Branch $branch): JsonResponse
    {
        Gate::authorize('update', $branch);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new BranchResource($this->service->setStatus($branch, ActiveStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        Gate::authorize('delete', $branch);
        $this->service->delete($branch, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    /**
     * Product availability at this store/branch.
     */
    public function inventory(Request $request, Branch $branch, InventoryService $inventory): JsonResponse
    {
        Gate::authorize('view', $branch);
        abort_unless($request->user()->can('inventory.view'), 403);

        $filters = ApiListing::filters($request, ['stock']) + ['branch_id' => $branch->id];

        return $this->successWithPagination(__('api.messages.retrieved'), InventoryBalanceResource::collection(ApiListing::paginate($inventory->balanceQuery($request->user(), $filters), $request, ['on_hand' => 'on_hand', 'updated_at' => 'source_updated_at'], '-source_updated_at')));
    }
}
