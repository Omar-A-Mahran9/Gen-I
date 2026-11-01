@extends('admin.layouts.app')

@section('title', $organization->name)

@section('toolbar')
    <x-admin.toolbar :title="$organization->name" :breadcrumbs="[__('admin.organizations.title') => route('admin.organizations.index'), $organization->name => null]">
        @can('update', $organization)
            @include('admin.business.partials.status-actions', [
                'url' => route('admin.organizations.status', $organization),
                'enum' => \App\Enums\OrganizationStatus::class,
                'targets' => [$organization->status === \App\Enums\OrganizationStatus::Active ? 'suspended' : 'active'],
            ])
            <x-admin.button :href="route('admin.organizations.edit', $organization)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $organization)
            <button type="button" class="btn btn-sm btn-light-danger" data-geni-action="{{ route('admin.organizations.destroy', $organization) }}" data-geni-method="DELETE"
                    data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i>{{ __('admin.common.delete') }}</button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.organizations.details')" icon="ki-office-bag" class="mb-5">
                @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $organization->status])->render()])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.timezone'), 'value' => e($organization->timezone), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.currency'), 'value' => e($organization->currency), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.common.created_at'), 'value' => e(format_datetime($organization->created_at))])
            </x-admin.card>
        </div>
        <div class="col-xl-8">
            <div class="row g-5 mb-5">
                @foreach ([
                    ['companies', 'ki-briefcase', 'admin.companies.index', 'companies.view'],
                    ['branches', 'ki-shop', 'admin.branches.index', 'branches.view'],
                    ['products', 'ki-handcart', 'admin.products.index', 'catalog.view'],
                    ['users', 'ki-people', 'admin.users.index', 'users.view'],
                ] as [$key, $icon, $route, $permission])
                    <div class="col-6 col-md-3">
                        <x-admin.stat-card :label="__('admin.organizations.stat.'.$key)" :value="$organization->{$key.'_count'}" :icon="$icon"
                            :href="auth()->user()->can($permission) ? route($route) : null" />
                    </div>
                @endforeach
            </div>
            <x-admin.card :title="__('admin.users.sections.activity')" icon="ki-time">
                @forelse ($activity as $log)
                    <div class="d-flex align-items-center gap-4 py-3 {{ $loop->last ? '' : 'border-bottom border-gray-200 border-bottom-dashed' }}">
                        @include('admin.audit-logs.partials.event-badge', ['event' => $log->event])
                        <div class="flex-grow-1 text-gray-700 fs-7">{{ $log->user?->name ?? __('admin.audit.system') }}</div>
                        <span class="text-muted fs-8">{{ format_datetime($log->created_at) }}</span>
                    </div>
                @empty
                    <x-admin.empty-state :title="__('admin.dashboard.recent_activity.empty')" compact />
                @endforelse
            </x-admin.card>
        </div>
    </div>
@endsection
