@extends('admin.layouts.app')

@php($roleLabel = \Illuminate\Support\Facades\Lang::has('admin.roles.names.'.$role->name) ? __('admin.roles.names.'.$role->name) : $role->name)

@section('title', $roleLabel)

@section('toolbar')
    <x-admin.toolbar :title="$roleLabel" :breadcrumbs="[__('admin.roles.title') => route('admin.roles.index'), $roleLabel => null]">
        @can('update', $role)
            <x-admin.button :href="route('admin.roles.edit', $role)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $role)
            <button type="button" class="btn btn-sm btn-light-danger"
                    data-geni-action="{{ route('admin.roles.destroy', $role) }}" data-geni-method="DELETE"
                    data-geni-confirm="{{ __('admin.roles.confirm.delete') }}" data-geni-confirm-danger="true">
                <i class="ki-outline ki-trash fs-4"></i>{{ __('admin.common.delete') }}
            </button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    @if ($isProtected)
        <x-admin.alert type="warning" icon="ki-lock" class="mb-5 mb-xl-8">{{ __('admin.roles.protected_notice') }}</x-admin.alert>
    @endif

    @php($granted = $role->permissions->pluck('name')->all())
    <div class="row g-5 g-xl-8">
        <div class="col-xl-8">
            <x-admin.card :title="__('admin.fields.permissions')" icon="ki-key"
                          :subtitle="__('admin.roles.permissions_count', ['count' => count($granted), 'total' => $groups->flatten()->count()])">
                <div class="d-flex flex-column gap-5">
                    @foreach ($groups as $group => $permissions)
                        <div class="d-flex flex-column flex-md-row gap-3 py-3 {{ $loop->last ? '' : 'border-bottom border-gray-200 border-bottom-dashed' }}">
                            <div class="fw-bold text-gray-800 w-md-200px flex-shrink-0">{{ __('admin.permissions.groups.'.$group) }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($permissions as $permission)
                                    @php($action = \Illuminate\Support\Str::after($permission->name, '.'))
                                    @php($has = in_array($permission->name, $granted, true))
                                    <span class="badge {{ $has ? 'badge-light-success' : 'badge-light text-gray-400' }} py-2 px-3">
                                        <i class="ki-outline {{ $has ? 'ki-check' : 'ki-cross' }} fs-7 me-1 {{ $has ? 'text-success' : 'text-gray-400' }}"></i>
                                        {{ \Illuminate\Support\Facades\Lang::has('admin.permissions.actions.'.$action) ? __('admin.permissions.actions.'.$action) : $action }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.roles.members')" icon="ki-people"
                          :subtitle="trans_choice('admin.roles.users_count', $usersCount, ['count' => $usersCount])">
                @forelse ($users as $member)
                    <div class="d-flex align-items-center gap-3 py-2">
                        <div class="symbol symbol-35px"><span class="symbol-label bg-light-primary text-primary fw-bold fs-7">{{ $member->initials() }}</span></div>
                        <div class="flex-grow-1 min-w-0">
                            @can('users.view')
                                <a href="{{ route('admin.users.show', $member) }}" class="text-gray-800 text-hover-primary fw-bold fs-7 d-block text-truncate">{{ $member->name }}</a>
                            @else
                                <span class="text-gray-800 fw-bold fs-7 d-block text-truncate">{{ $member->name }}</span>
                            @endcan
                            <span class="text-muted fs-8" dir="ltr">{{ $member->email }}</span>
                        </div>
                        <x-admin.status-badge :active="$member->is_active" />
                    </div>
                @empty
                    <x-admin.empty-state :title="__('admin.roles.no_members')" compact />
                @endforelse
                @if ($usersCount > $users->count())
                    <div class="text-muted fs-7 mt-3">{{ __('admin.roles.more_members', ['count' => $usersCount - $users->count()]) }}</div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
