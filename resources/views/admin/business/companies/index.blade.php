@extends('admin.layouts.app')

@section('title', __('admin.companies.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.companies.title')" :subtitle="__('admin.companies.subtitle')" :breadcrumbs="[__('admin.menu.business') => null, __('admin.companies.title') => null]">
        @can('create', \App\Models\Company::class)
            <x-admin.button data-geni-crud-create="#geni_company_modal" icon="ki-plus" size="sm">{{ __('admin.companies.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_companies_filters" search-id="geni_companies_search" :placeholder="__('admin.companies.search')" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\CompanyStatus::class" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_companies_table" :url="route('admin.companies.index')" filters="#geni_companies_filters" search="#geni_companies_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'legal_name', 'title' => __('admin.fields.legal_name'), 'class' => 'min-w-200px'],
                    ['data' => 'tax_reference', 'title' => __('admin.fields.tax_reference'), 'orderable' => false],
                    ['data' => 'account_owner', 'title' => __('admin.fields.account_owner_id'), 'orderable' => false],
                    ['data' => 'members', 'title' => __('admin.companies.members'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'created_at', 'title' => __('admin.common.created_at')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>

    @include('admin.business.companies.partials.form-modal', ['table' => '#geni_companies_table'])
@endsection
