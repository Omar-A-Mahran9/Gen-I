@extends('admin.layouts.app')

@section('title', __('admin.policies.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.policies.title')" :subtitle="__('admin.policies.subtitle')" :breadcrumbs="[__('admin.menu.pricing') => null, __('admin.policies.title') => null]">
        @can('create', \App\Models\PolicyVersion::class)
            <x-admin.button :href="route('admin.policies.create')" icon="ki-plus" size="sm">{{ __('admin.policies.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_policies_filters" search-id="geni_policies_search" :placeholder="__('admin.policies.search')" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\PolicyStatus::class" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_policies_table" :url="route('admin.policies.index')" filters="#geni_policies_filters" search="#geni_policies_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'policy_code', 'title' => __('admin.fields.policy_code')],
                    ['data' => 'version', 'title' => __('admin.fields.version')],
                    ['data' => 'effective_from', 'title' => __('admin.fields.effective_from')],
                    ['data' => 'approver', 'title' => __('admin.policies.approved_by'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>
@endsection
