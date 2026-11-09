<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Business\StorePolicyVersionRequest;
use App\Http\Requests\Business\UpdatePolicyVersionRequest;
use App\Http\Resources\Api\V1\PolicyVersionResource;
use App\Models\PolicyVersion;
use App\Services\Business\PolicyService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PolicyVersionController extends ApiController
{
    public function __construct(protected PolicyService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PolicyVersion::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'policy_code']));

        return $this->successWithPagination(__('api.messages.retrieved'), PolicyVersionResource::collection(ApiListing::paginate($query, $request, ['policy_code' => 'policy_code', 'version' => 'version', 'effective_from' => 'effective_from'], '-id')));
    }

    public function store(StorePolicyVersionRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new PolicyVersionResource($this->service->createDraft($request->payload(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(PolicyVersion $policy): JsonResponse
    {
        Gate::authorize('view', $policy);

        return $this->success(__('api.messages.retrieved'), new PolicyVersionResource($policy));
    }

    public function update(UpdatePolicyVersionRequest $request, PolicyVersion $policy): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new PolicyVersionResource($this->service->updateDraft($policy, $request->payload(), $request->user())));
    }

    public function approve(Request $request, PolicyVersion $policy): JsonResponse
    {
        Gate::authorize('approve', $policy);

        return $this->success(__('api.messages.approved'), new PolicyVersionResource($this->service->approve($policy, $request->user())));
    }

    public function retire(Request $request, PolicyVersion $policy): JsonResponse
    {
        Gate::authorize('retire', $policy);

        return $this->success(__('api.messages.retired'), new PolicyVersionResource($this->service->retire($policy, $request->user())));
    }

    public function destroy(Request $request, PolicyVersion $policy): JsonResponse
    {
        Gate::authorize('delete', $policy);
        $this->service->delete($policy, $request->user());

        return $this->success(__('api.messages.deleted'));
    }
}
