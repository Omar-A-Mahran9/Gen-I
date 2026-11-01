<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\ResetUserPasswordRequest;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\PrivilegeGuard;
use App\Services\Admin\UserService;
use App\Support\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $users,
        protected PrivilegeGuard $guard,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        if ($request->expectsJson()) {
            return $this->table($request);
        }

        return view('admin.users.index', [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'organizations' => $request->user()->isSuperAdmin() ? Organization::query()->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', [
            'user' => new User(['is_active' => true]),
            'assignableRoles' => $this->guard->assignableRoles($request->user()),
            'organizations' => $this->organizationOptions($request),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse|RedirectResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return $this->success($request, __('admin.users.messages.created'), route('admin.users.show', $user));
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load('roles'),
            'activity' => $request->user()->can('audit.view')
                ? AuditLog::query()
                    ->with('user:id,name')
                    ->where(fn (Builder $query) => $query
                        ->where('user_id', $user->id)
                        ->orWhere(fn (Builder $q) => $q->where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)))
                    ->latest('id')
                    ->limit(15)
                    ->get()
                : collect(),
            'isLastSuperAdmin' => $this->guard->isLastActiveSuperAdmin($user),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'assignableRoles' => $this->guard->assignableRoles($request->user()),
            'canAssignRoles' => $request->user()->can('assignRoles', $user),
            'organizations' => $this->organizationOptions($request),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        // Role changes apply only when the actor may assign roles to this user
        // and the role checkboxes were part of the submitted form.
        if ($request->user()->can('assignRoles', $user) && $request->boolean('roles_present')) {
            // Roles the actor cannot assign are not shown in the form, so they are kept as-is.
            $locked = $user->getRoleNames()->diff($this->guard->assignableRoles($request->user())->pluck('name'));
            $data['roles'] = collect($data['roles'] ?? [])->merge($locked)->unique()->values()->all();
        } else {
            unset($data['roles']);
        }

        $this->users->update($user, $data, $request->user());

        return $this->success($request, __('admin.users.messages.updated'), route('admin.users.show', $user));
    }

    public function toggleStatus(Request $request, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('toggleStatus', $user);

        $this->users->setActive($user, ! $user->is_active, $request->user());

        return $this->success(
            $request,
            $user->is_active ? __('admin.users.messages.activated') : __('admin.users.messages.deactivated'),
            data: ['is_active' => $user->is_active],
        );
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $this->users->resetPassword($user, $request->validated('password'), $request->user());

        return $this->success($request, __('admin.users.messages.password_reset'));
    }

    public function destroy(Request $request, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user, $request->user());

        return $this->success($request, __('admin.users.messages.deleted'), route('admin.users.index'));
    }

    /**
     * Lightweight search used by Select2 AJAX inputs.
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $term = trim((string) $request->input('q', ''));

        $results = User::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => ['id' => $user->id, 'text' => "{$user->name} ({$user->email})"]);

        return response()->json(['results' => $results]);
    }

    /**
     * @return array<int, string>
     */
    protected function organizationOptions(Request $request): array
    {
        return $request->user()->isSuperAdmin() ? Organization::query()->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    protected function table(Request $request): JsonResponse
    {
        $actor = $request->user();

        $query = User::query()->with(['roles:id,name', 'organization:id,name'])
            ->when(! $actor->isSuperAdmin(), fn (Builder $q) => $actor->organization_id === null
                ? $q->whereNull('organization_id')
                : $q->where('organization_id', $actor->organization_id));

        return DataTable::of($query, $request)
            ->searchable(['name', 'email'])
            ->sortable(['name' => 'name', 'email' => 'email', 'status' => 'is_active', 'last_login_at' => 'last_login_at', 'created_at' => 'created_at'])
            ->defaultOrder('created_at')
            ->filter(function (Builder $query, Request $request): void {
                $status = $request->input('filters.status');
                if (in_array($status, ['active', 'inactive'], true)) {
                    $query->where('is_active', $status === 'active');
                }

                if (is_numeric($organization = $request->input('filters.organization_id'))) {
                    $query->where('organization_id', (int) $organization);
                }

                $role = $request->input('filters.role');
                if (is_string($role) && $role !== '') {
                    $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
                }
            })
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => view('admin.users.partials.name-cell', ['user' => $user])->render(),
                'email' => e($user->email),
                'organization' => e($user->organization?->name ?? '—'),
                'roles' => view('admin.users.partials.roles-cell', ['user' => $user])->render(),
                'status' => view('admin.users.partials.status-cell', ['user' => $user])->render(),
                'last_login_at' => $user->last_login_at ? e(format_datetime($user->last_login_at)) : '<span class="text-muted">—</span>',
                'created_at' => e(format_date($user->created_at)),
                'actions' => view('admin.users.partials.actions-cell', ['user' => $user, 'actor' => $actor])->render(),
            ])
            ->toResponse();
    }
}
