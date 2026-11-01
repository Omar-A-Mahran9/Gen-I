@forelse ($user->roles as $role)
    <x-admin.role-badge :name="$role->name" />
@empty
    <span class="text-muted fs-7">{{ __('admin.users.no_roles') }}</span>
@endforelse
