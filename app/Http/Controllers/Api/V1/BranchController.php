<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\BranchRequest;
use App\Http\Resources\Api\V1\BranchResource;
use App\Http\Resources\Api\V1\InventoryBalanceResource;
use App\Models\Branch;
use App\Services\Business\BranchService;
use App\Services\Business\InventoryService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function __construct(protected BranchService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Branch::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'city']));

        return BranchResource::collection(ApiListing::paginate($query, $request, ['code' => 'code', 'name' => 'name', 'city' => 'city', 'created_at' => 'created_at'], 'code'));
    }

    public function store(BranchRequest $request): BranchResource
    {
        return new BranchResource($this->service->create($request->payload(), $request->user()));
    }

    public function show(Branch $branch): BranchResource
    {
        Gate::authorize('view', $branch);

        return new BranchResource($branch->loadCount('inventoryBalances'));
    }

    public function update(BranchRequest $request, Branch $branch): BranchResource
    {
        return new BranchResource($this->service->update($branch, $request->payload(), $request->user()));
    }

    public function status(Request $request, Branch $branch): BranchResource
    {
        Gate::authorize('update', $branch);
        $data = $request->validate(['status' => ['required', Rule::enum(ActiveStatus::class)]]);

        return new BranchResource($this->service->setStatus($branch, ActiveStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, Branch $branch): Response
    {
        Gate::authorize('delete', $branch);
        $this->service->delete($branch, $request->user());

        return response()->noContent();
    }

    /**
     * Product availability at this store/branch.
     */
    public function inventory(Request $request, Branch $branch, InventoryService $inventory): AnonymousResourceCollection
    {
        Gate::authorize('view', $branch);
        abort_unless($request->user()->can('inventory.view'), 403);

        $filters = ApiListing::filters($request, ['stock']) + ['branch_id' => $branch->id];

        return InventoryBalanceResource::collection(ApiListing::paginate($inventory->balanceQuery($request->user(), $filters), $request, ['on_hand' => 'on_hand', 'updated_at' => 'source_updated_at'], '-source_updated_at'));
    }
}
