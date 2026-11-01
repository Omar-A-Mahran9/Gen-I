@extends('admin.layouts.app')

@section('title', __('admin.products.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.products.title')" :subtitle="__('admin.products.subtitle')" :breadcrumbs="[__('admin.menu.catalog') => null, __('admin.products.title') => null]">
        @can('create', \App\Models\Product::class)
            <x-admin.button :href="route('admin.products.create')" icon="ki-plus" size="sm">{{ __('admin.products.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_products_filters" search-id="geni_products_search" :placeholder="__('admin.products.search')" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\ProductStatus::class" />
                <select name="category_id" class="form-select form-select-solid w-200px" aria-label="{{ __('admin.fields.category_id') }}">
                    <option value="">{{ __('admin.filters.all') }} · {{ __('admin.fields.category_id') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
                    @endforeach
                </select>
                <select name="brand_id" class="form-select form-select-solid w-175px" aria-label="{{ __('admin.fields.brand_id') }}">
                    <option value="">{{ __('admin.filters.all') }} · {{ __('admin.fields.brand_id') }}</option>
                    @foreach ($brands as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <x-admin.status-filter :enum="\App\Enums\ItemKind::class" name="item_kind" :label="__('admin.fields.item_kind')" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_products_table" :url="route('admin.products.index')" filters="#geni_products_filters" search="#geni_products_search"
                :order="[[5, 'desc']]"
                :columns="[
                    ['data' => 'name', 'title' => __('admin.fields.name'), 'class' => 'min-w-200px'],
                    ['data' => 'category', 'title' => __('admin.fields.category_id'), 'orderable' => false],
                    ['data' => 'brand', 'title' => __('admin.fields.brand_id'), 'orderable' => false],
                    ['data' => 'variants', 'title' => __('admin.products.variants'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'updated_at', 'title' => __('admin.common.updated_at')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>
@endsection
