<?php

namespace App\Http\Controllers\Admin\Business;

use App\Enums\OrganizationStatus;
use App\Http\Requests\Business\OrganizationRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\Business\OrganizationService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends BusinessController
{
    public function __construct(protected OrganizationService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        if (! $request->expectsJson()) {
            return view('admin.business.organizations.index');
        }

        return DataTable::of($this->service->query($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['name' => 'name', 'currency' => 'currency', 'status' => 'status', 'created_at' => 'created_at'])
            ->defaultOrder('name', 'asc')
            ->map(fn (Organization $o) => [
                'name' => '<a href="'.e(route('admin.organizations.show', $o)).'" class="text-gray-800 text-hover-primary fw-bold">'.e($o->name).'</a>',
                'timezone' => e($o->timezone),
                'currency' => e($o->currency),
                'counts' => e(__('admin.organizations.counts', ['companies' => $o->companies_count, 'branches' => $o->branches_count, 'users' => $o->users_count])),
                'status' => view('admin.business.partials.status-badge', ['status' => $o->status])->render(),
                'created_at' => e(format_date($o->created_at)),
                'actions' => view('admin.business.partials.row-actions', ['model' => $o, 'route' => 'organizations', 'param' => $o])->render(),
            ])
            ->toResponse();
    }

    public function create(): View
    {
        Gate::authorize('create', Organization::class);

        return view('admin.business.organizations.form', ['organization' => new Organization(config('admin.organization_defaults'))]);
    }

    public function store(OrganizationRequest $request): JsonResponse|RedirectResponse
    {
        $organization = $this->service->create($request->validated(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.organizations.show', $organization));
    }

    public function show(Organization $organization): View
    {
        Gate::authorize('view', $organization);

        return view('admin.business.organizations.show', [
            'organization' => $organization->loadCount(['companies', 'branches', 'users', 'products', 'categories']),
            'activity' => AuditLog::query()->with('user:id,name')->where('auditable_type', $organization->getMorphClass())->where('auditable_id', $organization->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Organization $organization): View
    {
        Gate::authorize('update', $organization);

        return view('admin.business.organizations.form', ['organization' => $organization]);
    }

    public function update(OrganizationRequest $request, Organization $organization): JsonResponse|RedirectResponse
    {
        $this->service->update($organization, $request->validated(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.organizations.show', $organization));
    }

    public function status(Request $request, Organization $organization): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $organization);
        $data = $request->validate(['status' => ['required', Rule::enum(OrganizationStatus::class)]]);
        $this->service->setStatus($organization, OrganizationStatus::from($data['status']), $request->user());

        return $this->success($request, __('admin.messages.status_changed'));
    }

    public function destroy(Request $request, Organization $organization): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $organization);
        $this->service->delete($organization, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.organizations.index'));
    }
}
