@extends('admin.layouts.app')

@section('title', __('admin.categories.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.categories.title')" :subtitle="__('admin.categories.subtitle')" :breadcrumbs="[__('admin.menu.catalog') => null, __('admin.categories.title') => null]">
        @can('create', \App\Models\Category::class)
            <x-admin.button data-geni-crud-create="#geni_category_modal" icon="ki-plus" size="sm">{{ __('admin.categories.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_categories_filters" search-id="geni_categories_search" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\ActiveStatus::class" />
                <select name="root" class="form-select form-select-solid w-175px" aria-label="{{ __('admin.categories.level') }}">
                    <option value="">{{ __('admin.categories.all_levels') }}</option>
                    <option value="1">{{ __('admin.categories.roots_only') }}</option>
                </select>
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_categories_table" :url="route('admin.categories.index')" filters="#geni_categories_filters" search="#geni_categories_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'code', 'title' => __('admin.fields.code')],
                    ['data' => 'name', 'title' => __('admin.fields.name')],
                    ['data' => 'parent', 'title' => __('admin.fields.parent_id'), 'orderable' => false],
                    ['data' => 'counts', 'title' => __('admin.categories.contents'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>

    @include('admin.business.categories.partials.form-modal', ['table' => '#geni_categories_table'])
@endsection
