@extends('admin.layouts.app')

@section('title', __('admin.brands.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.brands.title')" :subtitle="__('admin.brands.subtitle')" :breadcrumbs="[__('admin.menu.catalog') => null, __('admin.brands.title') => null]">
        @can('create', \App\Models\Brand::class)
            <x-admin.button data-geni-crud-create="#geni_brand_modal" icon="ki-plus" size="sm">{{ __('admin.brands.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_brands_filters" search-id="geni_brands_search" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\ActiveStatus::class" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_brands_table" :url="route('admin.brands.index')" filters="#geni_brands_filters" search="#geni_brands_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'name', 'title' => __('admin.fields.name')],
                    ['data' => 'products', 'title' => __('admin.organizations.stat.products'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'created_at', 'title' => __('admin.common.created_at')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-100px'],
                ]" />
        </div>
    </div>

    @include('admin.business.brands.partials.form-modal')
@endsection
