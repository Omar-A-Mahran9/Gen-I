<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyStatus;
use App\Http\Requests\Business\StoreAddressRequest;
use App\Http\Requests\Business\StoreCompanyMemberRequest;
use App\Http\Requests\Business\StoreCompanyRequest;
use App\Http\Requests\Business\UpdateAddressRequest;
use App\Http\Requests\Business\UpdateCompanyMemberRequest;
use App\Http\Requests\Business\UpdateCompanyRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Http\Resources\Api\V1\CompanyMemberResource;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Address;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Services\Business\CompanyService;
use App\Support\ApiListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyController extends ApiController
{
    public function __construct(protected CompanyService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Company::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'account_owner_id']));

        return $this->successWithPagination(__('api.messages.retrieved'), CompanyResource::collection(ApiListing::paginate($query, $request, ['legal_name' => 'legal_name', 'created_at' => 'created_at'], 'legal_name')));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        return $this->success(__('api.messages.created'), new CompanyResource($this->service->create($request->payload(), $request->user())), Response::HTTP_CREATED);
    }

    public function show(Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        return $this->success(__('api.messages.retrieved'), new CompanyResource($company->load(['accountOwner', 'members.user', 'addresses'])->loadCount(['members', 'addresses'])));
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        return $this->success(__('api.messages.updated'), new CompanyResource($this->service->update($company, $request->payload(), $request->user())));
    }

    public function status(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('update', $company);
        $data = $request->validate(['status' => ['required', Rule::enum(CompanyStatus::class)]]);

        return $this->success(__('api.messages.status_changed'), new CompanyResource($this->service->setStatus($company, CompanyStatus::from($data['status']), $request->user())));
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('delete', $company);
        $this->service->delete($company, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    public function members(Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        return $this->success(__('api.messages.retrieved'), CompanyMemberResource::collection($company->members()->with('user')->orderBy('id')->get()));
    }

    public function storeMember(StoreCompanyMemberRequest $request, Company $company): JsonResponse
    {
        return $this->success(__('api.messages.created'), new CompanyMemberResource($this->service->addMember($company, $request->validated(), $request->user())->load('user')), Response::HTTP_CREATED);
    }

    public function updateMember(UpdateCompanyMemberRequest $request, Company $company, CompanyMember $member): JsonResponse
    {
        abort_unless((int) $member->company_id === (int) $company->id, 404);

        return $this->success(__('api.messages.updated'), new CompanyMemberResource($this->service->updateMember($member, $request->validated(), $request->user())->load('user')));
    }

    public function destroyMember(Request $request, Company $company, CompanyMember $member): JsonResponse
    {
        Gate::authorize('update', $company);
        abort_unless((int) $member->company_id === (int) $company->id, 404);
        $this->service->removeMember($member, $request->user());

        return $this->success(__('api.messages.deleted'));
    }

    public function addresses(Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        return $this->success(__('api.messages.retrieved'), AddressResource::collection($company->addresses()->orderBy('label')->get()));
    }

    public function storeAddress(StoreAddressRequest $request, Company $company): JsonResponse
    {
        return $this->success(__('api.messages.created'), new AddressResource($this->service->saveAddress($company, $request->validated(), $request->user())), Response::HTTP_CREATED);
    }

    public function updateAddress(UpdateAddressRequest $request, Company $company, Address $address): JsonResponse
    {
        abort_unless((int) $address->company_id === (int) $company->id, 404);

        return $this->success(__('api.messages.updated'), new AddressResource($this->service->saveAddress($company, $request->validated(), $request->user(), $address)));
    }

    public function destroyAddress(Request $request, Company $company, Address $address): JsonResponse
    {
        Gate::authorize('update', $company);
        abort_unless((int) $address->company_id === (int) $company->id, 404);
        $this->service->deleteAddress($address, $request->user());

        return $this->success(__('api.messages.deleted'));
    }
}
