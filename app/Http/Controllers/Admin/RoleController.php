<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\RoleRequest;
use App\Services\Admin\PrivilegeGuard;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roles,
        protected PrivilegeGuard $guard,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
            'totalPermissions' => array_sum(array_map('count', config('admin.permissions'))),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', [
            'role' => new Role,
            'groups' => $this->roles->groupedPermissions(),
            'selected' => [],
            'grantable' => $this->grantable($request),
        ]);
    }

    public function store(RoleRequest $request): JsonResponse|RedirectResponse
    {
        $role = $this->roles->create($request->validated('name'), $request->validated('permissions', []), $request->user());

        return $this->success($request, __('admin.roles.messages.created'), route('admin.roles.show', $role));
    }

    public function show(Role $role): View
    {
        Gate::authorize('view', $role);

        return view('admin.roles.show', [
            'role' => $role->load('permissions'),
            'groups' => $this->roles->groupedPermissions(),
            'users' => $role->users()->orderBy('name')->limit(50)->get(['users.id', 'name', 'email', 'is_active']),
            'usersCount' => $role->users()->count(),
            'isProtected' => $this->guard->isProtectedRole($role),
        ]);
    }

    public function edit(Request $request, Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => $this->roles->groupedPermissions(),
            'selected' => $role->permissions->pluck('name')->all(),
            'grantable' => $this->grantable($request),
        ]);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->roles->update($role, $request->validated('name'), $request->validated('permissions', []), $request->user());

        return $this->success($request, __('admin.roles.messages.updated'), route('admin.roles.show', $role));
    }

    public function destroy(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roles->delete($role, $request->user());

        return $this->success($request, __('admin.roles.messages.deleted'), route('admin.roles.index'));
    }

    /**
     * Permission names the current user is allowed to grant (all for super admins).
     *
     * @return array<int, string>|null null means "all"
     */
    protected function grantable(Request $request): ?array
    {
        $user = $request->user();

        return $user->isSuperAdmin() ? null : $this->guard->permissionNamesOf($user)->all();
    }
}
