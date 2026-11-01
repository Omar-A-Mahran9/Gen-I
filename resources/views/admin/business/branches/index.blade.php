@extends('admin.layouts.app')

@section('title', __('admin.branches.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.branches.title')" :subtitle="__('admin.branches.subtitle')" :breadcrumbs="[__('admin.menu.business') => null, __('admin.branches.title') => null]">
        @can('create', \App\Models\Branch::class)
            <x-admin.button :href="route('admin.branches.create')" icon="ki-plus" size="sm">{{ __('admin.branches.add') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_branches_filters" search-id="geni_branches_search" :placeholder="__('admin.branches.search')" :organizations="$organizations">
                <x-admin.status-filter :enum="\App\Enums\ActiveStatus::class" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_branches_table" :url="route('admin.branches.index')" filters="#geni_branches_filters" search="#geni_branches_search"
                :order="[[0, 'asc']]"
                :columns="[
                    ['data' => 'code', 'title' => __('admin.fields.code')],
                    ['data' => 'name', 'title' => __('admin.fields.name'), 'class' => 'min-w-200px'],
                    ['data' => 'city', 'title' => __('admin.fields.city')],
                    ['data' => 'phone', 'title' => __('admin.fields.phone'), 'orderable' => false],
                    ['data' => 'items', 'title' => __('admin.branches.stock_items'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('admin.fields.status')],
                    ['data' => 'actions', 'title' => __('admin.common.actions'), 'orderable' => false, 'class' => 'text-end min-w-125px'],
                ]" />
        </div>
    </div>
@endsection
