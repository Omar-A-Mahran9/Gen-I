@extends('admin.layouts.app')

@section('title', __('admin.organizations.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.organizations.title')" :subtitle="__('admin.organizations.subtitle')" :breadcrumbs="[__('admin.menu.business') => null, __('admin.organizations.title') => null]">
        @can('create', \App\Models\Organization::class)
            <x-admin.button data-geni-crud-create="#geni_organization_modal" icon="ki-plus" size="sm">{{ __('admin.organizations.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_organizations_filters" search-id="geni_organizations_search">
                <x-admin.status-filter :enum="\App\Enums\OrganizationStatus::class" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_organizations_table" :url="route('admin.organizations.index')" filters="#geni_organizations_filters" search="#geni_organizations_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'name', 'title' => __('admin.fields.name')],
                    ['data' => 'timezone', 'title' => __('admin.fields.timezone'), 'orderable' => false],
                    ['data' => 'currency', 'title' => __('admin.fields.currency')],
                    ['data' => 'counts', 'title' => __('admin.organizations.records'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'created_at', 'title' => __('admin.common.created_at')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>

    @include('admin.business.organizations.partials.form-modal', ['table' => '#geni_organizations_table'])
@endsection
