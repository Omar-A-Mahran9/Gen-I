@extends('admin.layouts.app')

@section('title', __('admin.roles.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.roles.title')" :subtitle="__('admin.roles.subtitle')" :breadcrumbs="[__('admin.menu.access_control') => null, __('admin.roles.title') => null]">
        @can('create', \Spatie\Permission\Models\Role::class)
            <x-admin.button :href="route('admin.roles.create')" icon="ki-plus" size="sm">{{ __('admin.roles.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    @if ($roles->isEmpty())
        <div class="card"><div class="card-body">
            <x-admin.empty-state :title="__('admin.roles.empty.title')" :body="__('admin.roles.empty.body')" />
        </div></div>
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-5 g-xl-8">
            @foreach ($roles as $role)
                @php($protected = in_array($role->name, config('admin.protected_roles'), true))
                <div class="col">
                    <div class="card card-flush h-100" data-geni-role="{{ $role->name }}">
                        <div class="card-header">
                            <div class="card-title d-flex align-items-center gap-2">
                                <h2 class="mb-0 fs-4">{{ \Illuminate\Support\Facades\Lang::has('admin.roles.names.'.$role->name) ? __('admin.roles.names.'.$role->name) : $role->name }}</h2>
                                @if ($protected)
                                    <span class="badge badge-light-danger"><i class="ki-outline ki-lock fs-8 text-danger me-1"></i>{{ __('admin.roles.protected') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="card-body pt-1">
                            <div class="fw-bold text-gray-600 mb-2">{{ trans_choice('admin.roles.users_count', $role->users_count, ['count' => $role->users_count]) }}</div>
                            <div class="text-gray-500 fs-7 mb-4">{{ __('admin.roles.permissions_count', ['count' => $role->permissions_count, 'total' => $totalPermissions]) }}</div>
                            <div class="h-6px bg-light rounded">
                                <div class="bg-primary rounded h-6px" role="progressbar" style="width: {{ $totalPermissions ? round($role->permissions_count / $totalPermissions * 100) : 0 }}%"
                                     aria-valuenow="{{ $role->permissions_count }}" aria-valuemin="0" aria-valuemax="{{ $totalPermissions }}"></div>
                            </div>
                            <div class="text-muted fs-8 mt-3" dir="ltr">{{ $role->name }}</div>
                        </div>
                        <div class="card-footer flex-wrap pt-0 d-flex gap-2">
                            <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-light btn-active-primary btn-sm">{{ __('admin.common.view') }}</a>
                            @can('update', $role)
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-light btn-active-light-primary btn-sm">{{ __('admin.common.edit') }}</a>
                            @endcan
                            @can('delete', $role)
                                <button type="button" class="btn btn-light-danger btn-sm"
                                        data-geni-action="{{ route('admin.roles.destroy', $role) }}" data-geni-method="DELETE"
                                        data-geni-confirm="{{ __('admin.roles.confirm.delete') }}" data-geni-confirm-danger="true">{{ __('admin.common.delete') }}</button>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
