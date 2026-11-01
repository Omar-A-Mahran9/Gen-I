@extends('admin.layouts.app')

@section('title', __('admin.dashboard.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.dashboard.title')" :subtitle="__('admin.dashboard.subtitle')" />
@endsection

@section('content')
    @php
        $user = auth()->user();
    @endphp

    {{-- Welcome --}}
    <div class="card card-flush mb-5 mb-xl-8 bg-light-primary border-0">
        <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-5 py-8">
            <div class="d-flex align-items-center gap-5">
                <div class="symbol symbol-60px">
                    <span class="symbol-label fs-2 fw-bold bg-primary text-inverse-primary">{{ $user->initials() }}</span>
                </div>
                <div>
                    <h2 class="fw-bold text-gray-900 mb-1" data-geni-welcome>{{ __('admin.dashboard.welcome', ['name' => $user->name]) }}</h2>
                    <div class="text-gray-600 fw-semibold">{{ __('admin.dashboard.subtitle') }}</div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('create', \App\Models\User::class)
                    <x-admin.button :href="route('admin.users.create')" icon="ki-plus" variant="primary" size="sm">{{ __('admin.dashboard.quick_actions.add_user') }}</x-admin.button>
                @endcan
                <x-admin.button :href="route('admin.profile.edit')" icon="ki-user" variant="light-primary" size="sm">{{ __('admin.dashboard.quick_actions.profile') }}</x-admin.button>
            </div>
        </div>
    </div>

    {{-- Live statistics --}}
    <div class="row g-5 g-xl-8 mb-5 mb-xl-8">
        <div class="col-6 col-xl-3">
            <x-admin.stat-card :label="__('admin.dashboard.stats.users')" :value="$stats['users']" icon="ki-people" color="primary"
                               :href="$user->can('users.view') ? route('admin.users.index') : null" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat-card :label="__('admin.dashboard.stats.active_users')" :value="$stats['active_users']" icon="ki-user-tick" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat-card :label="__('admin.dashboard.stats.roles')" :value="$stats['roles']" icon="ki-shield-tick" color="info"
                               :href="$user->can('roles.view') ? route('admin.roles.index') : null" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat-card :label="__('admin.dashboard.stats.logins_today')" :value="$stats['logins_today']" icon="ki-entrance-left" color="warning" />
        </div>
    </div>

    <div class="row g-5 g-xl-8 mb-5 mb-xl-8">
        {{-- Account summary --}}
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.dashboard.account.title')" icon="ki-profile-circle" class="h-100">
                <div class="d-flex flex-column gap-5">
                    <div>
                        <div class="text-gray-500 fs-7 mb-1">{{ __('admin.fields.email') }}</div>
                        <div class="fw-bold text-gray-800" dir="ltr">{{ $user->email }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 fs-7 mb-1">{{ __('admin.dashboard.account.roles') }}</div>
                        @forelse ($user->getRoleNames() as $roleName)
                            <x-admin.role-badge :name="$roleName" />
                        @empty
                            <span class="text-muted">{{ __('admin.users.no_roles') }}</span>
                        @endforelse
                    </div>
                    <div class="d-flex gap-10">
                        <div>
                            <div class="text-gray-500 fs-7 mb-1">{{ __('admin.fields.status') }}</div>
                            <x-admin.status-badge :active="$user->is_active" />
                        </div>
                        <div>
                            <div class="text-gray-500 fs-7 mb-1">{{ __('admin.dashboard.account.unread') }}</div>
                            <a href="{{ route('admin.notifications.index') }}" class="fw-bold text-gray-800 text-hover-primary">{{ $unreadNotifications }}</a>
                        </div>
                    </div>
                    <div class="d-flex gap-10">
                        <div>
                            <div class="text-gray-500 fs-7 mb-1">{{ __('admin.dashboard.account.last_login') }}</div>
                            <div class="fw-semibold text-gray-800">{{ $user->last_login_at ? format_datetime($user->last_login_at) : __('admin.common.never') }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500 fs-7 mb-1">{{ __('admin.dashboard.account.member_since') }}</div>
                            <div class="fw-semibold text-gray-800">{{ format_date($user->created_at) }}</div>
                        </div>
                    </div>
                </div>
            </x-admin.card>
        </div>

        {{-- Quick actions (permission-aware) --}}
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.dashboard.quick_actions.title')" icon="ki-rocket" class="h-100">
                @php
                    $actions = array_filter([
                        $user->can('users.view') ? ['route' => 'admin.users.index', 'icon' => 'ki-people', 'label' => __('admin.dashboard.quick_actions.users'), 'color' => 'primary'] : null,
                        $user->can('users.create') ? ['route' => 'admin.users.create', 'icon' => 'ki-user-edit', 'label' => __('admin.dashboard.quick_actions.add_user'), 'color' => 'success'] : null,
                        $user->can('roles.create') ? ['route' => 'admin.roles.create', 'icon' => 'ki-shield-tick', 'label' => __('admin.dashboard.quick_actions.add_role'), 'color' => 'info'] : null,
                        $user->can('settings.view') ? ['route' => 'admin.settings.edit', 'icon' => 'ki-setting-2', 'label' => __('admin.dashboard.quick_actions.settings'), 'color' => 'warning'] : null,
                        $user->can('audit.view') ? ['route' => 'admin.audit-logs.index', 'icon' => 'ki-time', 'label' => __('admin.dashboard.quick_actions.audit'), 'color' => 'danger'] : null,
                        ['route' => 'admin.profile.edit', 'icon' => 'ki-user', 'label' => __('admin.dashboard.quick_actions.profile'), 'color' => 'dark'],
                    ]);
                @endphp
                <div class="row g-3">
                    @foreach ($actions as $action)
                        <div class="col-6">
                            <a href="{{ route($action['route']) }}" class="btn btn-flex btn-light-{{ $action['color'] }} w-100 flex-column align-items-center py-5 px-2 h-100">
                                <i class="ki-outline {{ $action['icon'] }} fs-2x mb-2 p-0"></i>
                                <span class="fs-7 fw-bold text-center">{{ $action['label'] }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>

        {{-- System status --}}
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.dashboard.system.title')" icon="ki-cloud" class="h-100">
                <div class="d-flex flex-column">
                    @foreach ($system as $key => $item)
                        <div class="d-flex flex-stack py-2 {{ $loop->last ? '' : 'border-bottom border-gray-200 border-bottom-dashed' }}">
                            <span class="text-gray-600 fw-semibold fs-7">{{ __("admin.dashboard.system.{$key}") }}</span>
                            <span class="d-flex align-items-center gap-2 fw-bold fs-7 text-gray-800" dir="ltr">
                                {{ $item['value'] }}
                                @if ($item['ok'] === true)
                                    <i class="ki-outline ki-check-circle fs-5 text-success"></i>
                                @elseif ($item['ok'] === false)
                                    <i class="ki-outline ki-information-5 fs-5 text-warning"></i>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        </div>
    </div>

    <div class="row g-5 g-xl-8 mb-5 mb-xl-8">
        {{-- Recent administrative activity --}}
        @can('audit.view')
            <div class="col-xl-8">
                <x-admin.card :title="__('admin.dashboard.recent_activity.title')" icon="ki-time" class="h-100">
                    <x-slot:toolbar>
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-light">{{ __('admin.dashboard.recent_activity.view_all') }}</a>
                    </x-slot:toolbar>
                    @forelse ($recentActivity as $log)
                        <div class="d-flex align-items-center gap-4 py-3 {{ $loop->last ? '' : 'border-bottom border-gray-200 border-bottom-dashed' }}">
                            @include('admin.audit-logs.partials.event-badge', ['event' => $log->event])
                            <div class="flex-grow-1 min-w-0 text-gray-700 fs-7 text-truncate">
                                {{ $log->user?->name ?? __('admin.audit.system') }}
                                @if ($log->auditable_type)
                                    <span class="text-muted">· {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                                @endif
                            </div>
                            <span class="text-muted fs-8 text-nowrap" title="{{ format_datetime($log->created_at) }}">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <x-admin.empty-state :title="__('admin.dashboard.recent_activity.empty')" compact />
                    @endforelse
                </x-admin.card>
            </div>
        @endcan

        {{-- Recent sign-ins --}}
        @can('users.view')
            <div class="col-xl-4">
                <x-admin.card :title="__('admin.dashboard.recent_logins.title')" icon="ki-entrance-left" class="h-100">
                    @forelse ($recentLogins as $login)
                        <div class="d-flex align-items-center gap-3 py-3 {{ $loop->last ? '' : 'border-bottom border-gray-200 border-bottom-dashed' }}">
                            <div class="symbol symbol-35px">
                                <span class="symbol-label bg-light-info text-info fw-bold fs-7">{{ $login->initials() }}</span>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('admin.users.show', $login) }}" class="text-gray-800 text-hover-primary fw-bold fs-7 d-block text-truncate">{{ $login->name }}</a>
                                <span class="text-muted fs-8" dir="ltr">{{ $login->last_login_ip }}</span>
                            </div>
                            <span class="text-muted fs-8 text-nowrap" title="{{ format_datetime($login->last_login_at) }}">{{ $login->last_login_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <x-admin.empty-state :title="__('admin.dashboard.recent_logins.empty')" compact />
                    @endforelse
                </x-admin.card>
            </div>
        @endcan
    </div>

    {{-- Business overview: live counts for the modules the user may access (no sample data). --}}
    @if (! empty($business))
        <x-admin.card :title="__('admin.dashboard.business.title')" icon="ki-chart-simple" class="mb-5 mb-xl-8">
            <div class="row g-5">
                @foreach ($business as $key => $stat)
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route($stat['route']) }}" class="d-block border border-dashed border-gray-300 rounded p-5 text-hover-primary h-100" data-geni-business-stat="{{ $key }}">
                            <i class="ki-outline {{ $stat['icon'] }} fs-2x text-primary mb-3 d-block"></i>
                            <div class="fs-2 fw-bold text-gray-900">{{ number_format($stat['value']) }}</div>
                            <div class="text-gray-500 fs-7">{{ __('admin.dashboard.business.'.$key) }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </x-admin.card>
    @endif
@endsection
