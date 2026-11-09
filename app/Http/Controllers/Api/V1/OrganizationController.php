<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationStatus;
use App\Http\Requests\Business\StoreOrganizationRequest;
use App\Http\Requests\Business\UpdateOrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use App\Services\Business\OrganizationService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrganizationController extends ApiController
{
    public function __construct(protected OrganizationService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'currency']));

        return $this->successWithPagination(__('api.messages.retrieved'), OrganizationResource::collection(ApiListing::paginate($query, $request, ['name' => 'name', 'created_at' => 'created_at'], 'name')));
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new OrganizationResource($this->service->create($request->validated(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(Organization $organization): JsonResponse
    {
        Gate::authorize('view', $organization);

        return $this->success(__('api.messages.retrieved'), new OrganizationResource($organization->loadCount(['companies', 'branches', 'users'])));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new OrganizationResource($this->service->update($organization, $request->validated(), $request->user())));
    }

    public function status(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $data = $request->validate(['status' => ['required', Rule::enum(OrganizationStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new OrganizationResource($this->service->setStatus($organization, OrganizationStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);
        $this->service->delete($organization, $request->user());

        return $this->success(__('api.messages.deleted'));
    }
}
