@extends('admin.layouts.app')

@section('title', __('admin.users.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.users.title')" :subtitle="__('admin.users.subtitle')" :breadcrumbs="[__('admin.menu.access_control') => null, __('admin.users.title') => null]">
        @can('create', \App\Models\User::class)
            <x-admin.button :href="route('admin.users.create')" icon="ki-plus" size="sm">{{ __('admin.users.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <form id="geni_users_filters" class="card-title flex-wrap gap-3 w-100" role="search" onsubmit="return false">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5"></i>
                    <input type="search" id="geni_users_search" class="form-control form-control-solid w-250px ps-13"
                           placeholder="{{ __('admin.users.search_placeholder') }}" aria-label="{{ __('admin.users.search_placeholder') }}" />
                </div>
                <select name="status" class="form-select form-select-solid w-175px" aria-label="{{ __('admin.users.filters.status') }}">
                    <option value="">{{ __('admin.users.filters.all_statuses') }}</option>
                    <option value="active">{{ __('admin.common.active') }}</option>
                    <option value="inactive">{{ __('admin.common.inactive') }}</option>
                </select>
                <select name="role" class="form-select form-select-solid w-200px" aria-label="{{ __('admin.users.filters.role') }}">
                    <option value="">{{ __('admin.users.filters.all_roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ \Illuminate\Support\Facades\Lang::has("admin.roles.names.{$role}") ? __("admin.roles.names.{$role}") : $role }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_users_table" :url="route('admin.users.index')" filters="#geni_users_filters" search="#geni_users_search"
                :order="[[4, 'desc']]"
                :columns="[
                    ['data' => 'name', 'title' => __('admin.users.columns.name'), 'class' => 'min-w-200px'],
                    ['data' => 'roles', 'title' => __('admin.users.columns.roles'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.users.columns.status')],
                    ['data' => 'last_login_at', 'title' => __('admin.users.columns.last_login')],
                    ['data' => 'created_at', 'title' => __('admin.users.columns.created_at')],
                    ['data' => 'actions', 'title' => __('admin.users.columns.actions'), 'orderable' => false, 'class' => 'text-end min-w-100px'],
                ]" />
        </div>
    </div>
@endsection
