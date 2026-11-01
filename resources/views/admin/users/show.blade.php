@extends('admin.layouts.app')

@section('title', $user->name)

@section('toolbar')
    <x-admin.toolbar :title="__('admin.users.show_title')" :breadcrumbs="[__('admin.users.title') => route('admin.users.index'), $user->name => null]">
        @can('update', $user)
            <x-admin.button :href="route('admin.users.edit', $user)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    @if ($isLastSuperAdmin)
        <x-admin.alert type="warning" class="mb-5 mb-xl-8">{{ __('admin.users.last_super_admin_notice') }}</x-admin.alert>
    @endif

    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <div class="card mb-5 mb-xl-8">
                <div class="card-body pt-12 d-flex flex-column align-items-center text-center">
                    <div class="symbol symbol-100px symbol-circle mb-5">
                        <span class="symbol-label fs-2x fw-bold bg-light-primary text-primary">{{ $user->initials() }}</span>
                    </div>
                    <div class="fs-3 text-gray-900 fw-bold mb-1">{{ $user->name }}</div>
                    <div class="fs-6 text-gray-500 mb-4" dir="ltr">{{ $user->email }}</div>
                    <div class="mb-4">
                        @forelse ($user->roles as $role)
                            <x-admin.role-badge :name="$role->name" />
                        @empty
                            <span class="text-muted">{{ __('admin.users.no_roles') }}</span>
                        @endforelse
                    </div>
                    <x-admin.status-badge :active="$user->is_active" />
                    @if (auth()->user()->is($user))
                        <span class="badge badge-light-info mt-2">{{ __('admin.common.you') }}</span>
                    @endif
                </div>
                <div class="card-body border-top pt-6">
                    <div class="d-flex flex-column gap-4 fs-7">
                        <div class="d-flex flex-stack"><span class="text-gray-500">{{ __('admin.fields.locale') }}</span><span class="fw-bold">{{ $user->locale ? config('admin.locales.'.$user->locale.'.name') : '—' }}</span></div>
                        <div class="d-flex flex-stack"><span class="text-gray-500">{{ __('admin.users.columns.last_login') }}</span><span class="fw-bold">{{ $user->last_login_at ? format_datetime($user->last_login_at) : __('admin.common.never') }}</span></div>
                        <div class="d-flex flex-stack"><span class="text-gray-500">IP</span><span class="fw-bold" dir="ltr">{{ $user->last_login_ip ?? '—' }}</span></div>
                        <div class="d-flex flex-stack"><span class="text-gray-500">{{ __('admin.common.created_at') }}</span><span class="fw-bold">{{ format_datetime($user->created_at) }}</span></div>
                        <div class="d-flex flex-stack"><span class="text-gray-500">{{ __('admin.common.updated_at') }}</span><span class="fw-bold">{{ format_datetime($user->updated_at) }}</span></div>
                    </div>
                </div>
            </div>

            {{-- Security actions --}}
            @if (auth()->user()->can('toggleStatus', $user) || auth()->user()->can('resetPassword', $user) || auth()->user()->can('delete', $user))
                <x-admin.card :title="__('admin.users.sections.security')" icon="ki-lock" class="mb-5 mb-xl-8">
                    <div class="d-flex flex-column gap-3">
                        @can('resetPassword', $user)
                            <button type="button" class="btn btn-light-primary w-100" data-bs-toggle="modal" data-bs-target="#geni_reset_password_modal">
                                <i class="ki-outline ki-key fs-4"></i>{{ __('admin.users.reset_password.button') }}
                            </button>
                        @endcan
                        @can('toggleStatus', $user)
                            <button type="button" class="btn {{ $user->is_active ? 'btn-light-warning' : 'btn-light-success' }} w-100"
                                    data-geni-action="{{ route('admin.users.status', $user) }}" data-geni-method="PATCH" data-geni-reload="true"
                                    data-geni-confirm="{{ $user->is_active ? __('admin.users.confirm.deactivate') : __('admin.users.confirm.activate') }}">
                                <i class="ki-outline {{ $user->is_active ? 'ki-lock' : 'ki-lock-2' }} fs-4"></i>{{ $user->is_active ? __('admin.users.deactivate') : __('admin.users.activate') }}
                            </button>
                        @endcan
                        @can('delete', $user)
                            <button type="button" class="btn btn-light-danger w-100"
                                    data-geni-action="{{ route('admin.users.destroy', $user) }}" data-geni-method="DELETE"
                                    data-geni-confirm="{{ __('admin.users.confirm.delete') }}" data-geni-confirm-danger="true">
                                <i class="ki-outline ki-trash fs-4"></i>{{ __('admin.common.delete') }}
                            </button>
                        @endcan
                    </div>
                </x-admin.card>
            @endif
        </div>

        <div class="col-xl-8">
            @can('audit.view')
                <x-admin.card :title="__('admin.users.sections.activity')" icon="ki-time">
                    @forelse ($activity as $log)
                        <div class="d-flex align-items-center gap-4 py-3 {{ $loop->last ? '' : 'border-bottom border-gray-200 border-bottom-dashed' }}">
                            @include('admin.audit-logs.partials.event-badge', ['event' => $log->event])
                            <div class="flex-grow-1 min-w-0 fs-7 text-gray-700 text-truncate">{{ $log->user?->name ?? __('admin.audit.system') }}</div>
                            <span class="text-muted fs-8 text-nowrap" title="{{ format_datetime($log->created_at) }}">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <x-admin.empty-state :title="__('admin.dashboard.recent_activity.empty')" compact />
                    @endforelse
                </x-admin.card>
            @endcan
        </div>
    </div>

    @can('resetPassword', $user)
        @push('modals')
            <x-admin.modal id="geni_reset_password_modal" :title="__('admin.users.reset_password.title')">
                <form method="POST" action="{{ route('admin.users.password', $user) }}" data-geni-ajax-form data-geni-close-modal="#geni_reset_password_modal" novalidate>
                    @csrf
                    @method('PUT')
                    <x-admin.alert type="info" class="mb-7">{{ __('admin.users.reset_password.help') }}</x-admin.alert>
                    <x-admin.form.input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="new-password" dir="ltr" :hint="__('admin.users.password_hint')" id="reset_password_input" />
                    <x-admin.form.input name="password_confirmation" type="password" :label="__('admin.fields.password_confirmation')" required autocomplete="new-password" dir="ltr" id="reset_password_confirmation_input" />
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-key">{{ __('admin.users.reset_password.submit') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>
        @endpush
    @endcan
@endsection
