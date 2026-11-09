<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Requests\Business\StorePolicyVersionRequest;
use App\Http\Requests\Business\UpdatePolicyVersionRequest;
use App\Models\PolicyVersion;
use App\Services\Business\PolicyService;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PolicyVersionController extends BusinessController
{
    public function __construct(protected PolicyService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', PolicyVersion::class);

        if (! $request->expectsJson()) {
            return view('admin.business.policies.index', ['organizations' => $this->organizationOptions($request->user())]);
        }

        return DataTable::of($this->service->query($request->user(), $this->tableFilters($request)), $request)
            ->sortable(['policy_code' => 'policy_code', 'version' => 'version', 'effective_from' => 'effective_from', 'status' => 'status'])
            ->defaultOrder('id')
            ->map(fn (PolicyVersion $p) => [
                'policy_code' => '<a href="'.e(route('admin.policies.show', $p)).'" class="text-gray-800 text-hover-primary fw-bold" dir="ltr">'.e($p->policy_code).'</a><div class="text-muted fs-7">'.e($p->organization?->name).'</div>',
                'version' => 'v'.(int) $p->version,
                'effective_from' => e(format_datetime($p->effective_from)),
                'approver' => e($p->approver?->name ?? '—'),
                'status' => view('admin.business.partials.status-badge', ['status' => $p->status])->render(),
                'actions' => view('admin.business.partials.row-actions', ['model' => $p, 'route' => 'policies', 'param' => $p])->render(),
            ])
            ->toResponse();
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PolicyVersion::class);

        return view('admin.business.policies.form', ['policy' => new PolicyVersion(['effective_from' => now()]), 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function store(StorePolicyVersionRequest $request): JsonResponse|RedirectResponse
    {
        $policy = $this->service->createDraft($request->payload(), $request->user());

        return $this->success($request, __('admin.messages.created'), route('admin.policies.show', $policy));
    }

    public function show(PolicyVersion $policy): View
    {
        Gate::authorize('view', $policy);

        return view('admin.business.policies.show', ['policy' => $policy->load(['organization', 'author', 'approver'])]);
    }

    public function edit(Request $request, PolicyVersion $policy): View
    {
        Gate::authorize('update', $policy);

        return view('admin.business.policies.form', ['policy' => $policy, 'organizations' => $this->organizationOptions($request->user())]);
    }

    public function update(UpdatePolicyVersionRequest $request, PolicyVersion $policy): JsonResponse|RedirectResponse
    {
        $this->service->updateDraft($policy, $request->payload(), $request->user());

        return $this->success($request, __('admin.messages.updated'), route('admin.policies.show', $policy));
    }

    public function approve(Request $request, PolicyVersion $policy): JsonResponse|RedirectResponse
    {
        Gate::authorize('approve', $policy);
        $this->service->approve($policy, $request->user());

        return $this->success($request, __('admin.policies.messages.approved'));
    }

    public function retire(Request $request, PolicyVersion $policy): JsonResponse|RedirectResponse
    {
        Gate::authorize('retire', $policy);
        $this->service->retire($policy, $request->user());

        return $this->success($request, __('admin.policies.messages.retired'));
    }

    public function destroy(Request $request, PolicyVersion $policy): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $policy);
        $this->service->delete($policy, $request->user());

        return $this->success($request, __('admin.messages.deleted'), route('admin.policies.index'));
    }
}
