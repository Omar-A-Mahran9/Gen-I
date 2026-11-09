@extends('admin.layouts.app')

@section('title', __('admin.pricing.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.pricing.title')" :subtitle="__('admin.pricing.subtitle')" :breadcrumbs="[__('admin.menu.pricing') => null, __('admin.pricing.title') => null]">
        @can('create', \App\Models\PriceList::class)
            <x-admin.button data-geni-crud-create="#geni_price_list_modal" icon="ki-plus" size="sm">{{ __('admin.pricing.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_price_lists_filters" search-id="geni_price_lists_search" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\ActiveStatus::class" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_price_lists_table" :url="route('admin.price-lists.index')" filters="#geni_price_lists_filters" search="#geni_price_lists_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'code', 'title' => __('admin.fields.code')],
                    ['data' => 'currency', 'title' => __('admin.fields.currency')],
                    ['data' => 'prices', 'title' => __('admin.pricing.prices'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>

    @include('admin.business.price-lists.partials.form-modal', ['table' => '#geni_price_lists_table'])
@endsection
