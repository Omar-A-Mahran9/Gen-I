<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\AddressRequest;
use App\Http\Requests\Business\CompanyMemberRequest;
use App\Http\Requests\Business\CompanyRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Http\Resources\Api\V1\CompanyMemberResource;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Address;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Services\Business\CompanyService;
use App\Support\ApiListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function __construct(protected CompanyService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Company::class);
        $query = $this->service->query($request->user(), ApiListing::filters($request, ['status', 'organization_id', 'account_owner_id']));

        return CompanyResource::collection(ApiListing::paginate($query, $request, ['legal_name' => 'legal_name', 'created_at' => 'created_at'], 'legal_name'));
    }

    public function store(CompanyRequest $request): CompanyResource
    {
        return new CompanyResource($this->service->create($request->payload(), $request->user()));
    }

    public function show(Company $company): CompanyResource
    {
        Gate::authorize('view', $company);

        return new CompanyResource($company->load(['accountOwner', 'members.user', 'addresses'])->loadCount(['members', 'addresses']));
    }

    public function update(CompanyRequest $request, Company $company): CompanyResource
    {
        return new CompanyResource($this->service->update($company, $request->payload(), $request->user()));
    }

    public function status(Request $request, Company $company): CompanyResource
    {
        Gate::authorize('update', $company);
        $data = $request->validate(['status' => ['required', Rule::enum(CompanyStatus::class)]]);

        return new CompanyResource($this->service->setStatus($company, CompanyStatus::from($data['status']), $request->user()));
    }

    public function destroy(Request $request, Company $company): Response
    {
        Gate::authorize('delete', $company);
        $this->service->delete($company, $request->user());

        return response()->noContent();
    }

    public function members(Company $company): AnonymousResourceCollection
    {
        Gate::authorize('view', $company);

        return CompanyMemberResource::collection($company->members()->with('user')->orderBy('id')->get());
    }

    public function storeMember(CompanyMemberRequest $request, Company $company): CompanyMemberResource
    {
        return new CompanyMemberResource($this->service->addMember($company, $request->validated(), $request->user())->load('user'));
    }

    public function updateMember(CompanyMemberRequest $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        abort_unless((int) $member->company_id === (int) $company->id, 404);

        return new CompanyMemberResource($this->service->updateMember($member, $request->validated(), $request->user())->load('user'));
    }

    public function destroyMember(Request $request, Company $company, CompanyMember $member): Response
    {
        Gate::authorize('update', $company);
        abort_unless((int) $member->company_id === (int) $company->id, 404);
        $this->service->removeMember($member, $request->user());

        return response()->noContent();
    }

    public function addresses(Company $company): AnonymousResourceCollection
    {
        Gate::authorize('view', $company);

        return AddressResource::collection($company->addresses()->orderBy('label')->get());
    }

    public function storeAddress(AddressRequest $request, Company $company): AddressResource
    {
        return new AddressResource($this->service->saveAddress($company, $request->validated(), $request->user()));
    }

    public function updateAddress(AddressRequest $request, Company $company, Address $address): AddressResource
    {
        abort_unless((int) $address->company_id === (int) $company->id, 404);

        return new AddressResource($this->service->saveAddress($company, $request->validated(), $request->user(), $address));
    }

    public function destroyAddress(Request $request, Company $company, Address $address): Response
    {
        Gate::authorize('update', $company);
        abort_unless((int) $address->company_id === (int) $company->id, 404);
        $this->service->deleteAddress($address, $request->user());

        return response()->noContent();
    }
}
