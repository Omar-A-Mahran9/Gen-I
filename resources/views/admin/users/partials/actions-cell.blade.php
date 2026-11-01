{{-- Row actions; each option is shown only when the policy allows it (and the backend re-checks). --}}
<button type="button" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm"
        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end" aria-label="{{ __('admin.common.actions') }}">
    {{ __('admin.common.actions') }}
    <i class="ki-outline ki-down fs-5 ms-1"></i>
</button>
<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-175px py-4" data-kt-menu="true">
    <div class="menu-item px-3">
        <a href="{{ route('admin.users.show', $user) }}" class="menu-link px-3">{{ __('admin.common.view') }}</a>
    </div>
    @can('update', $user)
        <div class="menu-item px-3">
            <a href="{{ route('admin.users.edit', $user) }}" class="menu-link px-3">{{ __('admin.common.edit') }}</a>
        </div>
    @endcan
    @can('toggleStatus', $user)
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3"
               data-geni-action="{{ route('admin.users.status', $user) }}" data-geni-method="PATCH"
               data-geni-confirm="{{ $user->is_active ? __('admin.users.confirm.deactivate') : __('admin.users.confirm.activate') }}"
               data-geni-table="#geni_users_table">
                {{ $user->is_active ? __('admin.users.deactivate') : __('admin.users.activate') }}
            </a>
        </div>
    @endcan
    @can('delete', $user)
        <div class="menu-item px-3">
            <a href="#" class="menu-link px-3 text-danger"
               data-geni-action="{{ route('admin.users.destroy', $user) }}" data-geni-method="DELETE"
               data-geni-confirm="{{ __('admin.users.confirm.delete') }}" data-geni-confirm-danger="true"
               data-geni-table="#geni_users_table">{{ __('admin.common.delete') }}</a>
        </div>
    @endcan
</div>
