<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\PolicyVersionRequest;
use App\Http\Resources\Api\V1\PolicyVersionResource;
use App\Models\PolicyVersion;
use App\Services\Business\PolicyService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PolicyVersionController extends Controller
{
    public function __construct(protected PolicyService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PolicyVersion::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'policy_code']));

        return PolicyVersionResource::collection(ApiListing::paginate($query, $request, ['policy_code' => 'policy_code', 'version' => 'version', 'effective_from' => 'effective_from'], '-id'));
    }

    public function store(PolicyVersionRequest $request): PolicyVersionResource
    {
        return new PolicyVersionResource($this->service->createDraft($request->payload(), $request->user()));
    }

    public function show(PolicyVersion $policy): PolicyVersionResource
    {
        Gate::authorize('view', $policy);

        return new PolicyVersionResource($policy);
    }

    public function update(PolicyVersionRequest $request, PolicyVersion $policy): PolicyVersionResource
    {
        return new PolicyVersionResource($this->service->updateDraft($policy, $request->payload(), $request->user()));
    }

    public function approve(Request $request, PolicyVersion $policy): PolicyVersionResource
    {
        Gate::authorize('approve', $policy);

        return new PolicyVersionResource($this->service->approve($policy, $request->user()));
    }

    public function retire(Request $request, PolicyVersion $policy): PolicyVersionResource
    {
        Gate::authorize('retire', $policy);

        return new PolicyVersionResource($this->service->retire($policy, $request->user()));
    }

    public function destroy(Request $request, PolicyVersion $policy): Response
    {
        Gate::authorize('delete', $policy);
        $this->service->delete($policy, $request->user());

        return response()->noContent();
    }
}
