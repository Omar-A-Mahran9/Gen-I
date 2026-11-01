<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\OrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use App\Services\Business\OrganizationService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(protected OrganizationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Organization::class);

        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'currency']));

        return OrganizationResource::collection(ApiListing::paginate($query, $request, ['name' => 'name', 'created_at' => 'created_at'], 'name'));
    }

    public function store(OrganizationRequest $request): OrganizationResource
    {
        return new OrganizationResource($this->service->create($request->validated(), $request->user()));
    }

    public function show(Organization $organization): OrganizationResource
    {
        Gate::authorize('view', $organization);

        return new OrganizationResource($organization->loadCount(['companies', 'branches', 'users']));
    }

    public function update(OrganizationRequest $request, Organization $organization): OrganizationResource
    {
        return new OrganizationResource($this->service->update($organization, $request->validated(), $request->user()));
    }

    public function status(Request $request, Organization $organization): OrganizationResource
    {
        Gate::authorize('update', $organization);
        $data = $request->validate(['status' => ['required', Rule::enum(OrganizationStatus::class)]]);

        return new OrganizationResource($this->service->setStatus($organization, OrganizationStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, Organization $organization): Response
    {
        Gate::authorize('delete', $organization);
        $this->service->delete($organization, $request->user());

        return response()->noContent();
    }
}
