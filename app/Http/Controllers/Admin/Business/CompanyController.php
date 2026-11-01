<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\CompanyStatus;
use App\Http\Requests\Business\AddressRequest;
use App\Http\Requests\Business\CompanyMemberRequest;
use App\Http\Requests\Business\CompanyRequest;
use App\Models\Address;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use App\Services\Business\CompanyService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends BusinessController
{
    public function __construct(protected CompanyService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Company::class);

        if (! $request->expectsJson()) {
            return view('admin.business.companies.index', ['organizations' => $this->organizationOptions($request->user())]);
        }

        return DataTable::of($this->service->query($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['legal_name' => 'legal_name', 'status' => 'status', 'created_at' => 'created_at'])
            ->defaultOrder('legal_name', 'asc')
            ->map(fn (Company $c) => [
                'legal_name' => '<a href="'.e(route('admin.companies.show', $c)).'" class="text-gray-800 text-hover-primary fw-bold">'.e($c->legal_name).'</a><div class="text-muted fs-7">'.e($c->organization?->name).'</div>',
                'tax_reference' => e($c->tax_reference ?? '—'),
                'account_owner' => e($c->accountOwner?->name ?? '—'),
                'members' => (int) $c->members_count,
                'status' => view('admin.business.partials.status-badge', ['status' => $c->status])->render(),
                'created_at' => e(format_date($c->created_at)),
                'actions' => view('admin.business.partials.row-actions', ['model' => $c, 'route' => 'companies', 'param' => $c])->render(),
            ])
            ->toResponse();
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Company::class);

        return view('admin.business.companies.form', $this->formData($request, new Company));
    }

    public function store(CompanyRequest $request): JsonResponse|RedirectResponse
    {
        $company = $this->service->create($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.companies.show', $company));
    }

    public function show(Request $request, Company $company): View
    {
        Gate::authorize('view', $company);

        return view('admin.business.companies.show', [
            'company' => $company->load(['organization', 'accountOwner', 'members.user', 'addresses']),
            'memberCandidates' => User::query()->where('organization_id', $company->organization_id)
                ->whereNotIn('id', $company->members->pluck('user_id'))->orderBy('name')->get(['id', 'name', 'email']),
            'transitions' => CompanyService::TRANSITIONS[$company->status->value] ?? [],
        ]);
    }

    public function edit(Request $request, Company $company): View
    {
        Gate::authorize('update', $company);

        return view('admin.business.companies.form', $this->formData($request, $company));
    }

    public function update(CompanyRequest $request, Company $company): JsonResponse|RedirectResponse
    {
        $this->service->update($company, $request->payload(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.companies.show', $company));
    }

    public function status(Request $request, Company $company): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $company);
        $data = $request->validate(['status' => ['required', Rule::enum(CompanyStatus::class)]]);
        $this->service->setStatus($company, CompanyStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, Company $company): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $company);
        $this->service->delete($company, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.companies.index'));
    }

    public function storeMember(CompanyMemberRequest $request, Company $company): JsonResponse|RedirectResponse
    {
        $this->service->addMember($company, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.companies.show', $company));
    }

    public function updateMember(CompanyMemberRequest $request, Company $company, CompanyMember $member): JsonResponse|RedirectResponse
    {
        abort_unless((int) $member->company_id === (int) $company->id, 404);
        $this->service->updateMember($member, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.companies.show', $company));
    }

    public function destroyMember(Request $request, Company $company, CompanyMember $member): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $company);
        abort_unless((int) $member->company_id === (int) $company->id, 404);
        $this->service->removeMember($member, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.companies.show', $company));
    }

    public function storeAddress(AddressRequest $request, Company $company): JsonResponse|RedirectResponse
    {
        $this->service->saveAddress($company, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.companies.show', $company));
    }

    public function updateAddress(AddressRequest $request, Company $company, Address $address): JsonResponse|RedirectResponse
    {
        abort_unless((int) $address->company_id === (int) $company->id, 404);
        $this->service->saveAddress($company, $request->validated(), $request->user(), $address);

        return $this->success($request, __('admin.messages.updated'), route('admin.companies.show', $company));
    }

    public function destroyAddress(Request $request, Company $company, Address $address): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $company);
        abort_unless((int) $address->company_id === (int) $company->id, 404);
        $this->service->deleteAddress($address, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.companies.show', $company));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Request $request, Company $company): array
    {
        $orgId = $company->organization_id ?? $request->user()->organization_id;

        return [
            'company' => $company,
            'organizations' => $this->organizationOptions($request->user()),
            'owners' => User::query()
                ->when(! $request->user()->isSuperAdmin() || $orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->whereNotNull('organization_id')
                ->orderBy('name')->get(['id', 'name', 'email', 'organization_id']),
        ];
    }
}
