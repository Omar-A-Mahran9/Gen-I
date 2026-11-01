@extends('admin.layouts.app')

@section('title', __('admin.inventory.movements_title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.inventory.movements_title')" :subtitle="__('admin.inventory.movements_subtitle')" :breadcrumbs="[__('admin.menu.inventory') => route('admin.inventory.index'), __('admin.menu.movements') => null]" />
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_movements_filters" search-id="geni_movements_search" :placeholder="__('admin.inventory.search_movements')">
                <select name="branch_id" class="form-select form-select-solid w-200px" aria-label="{{ __('admin.fields.branch_id') }}">
                    <option value="">{{ __('admin.filters.all') }} · {{ __('admin.fields.branch_id') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
                <select name="reason" class="form-select form-select-solid w-175px" aria-label="{{ __('admin.fields.reason') }}">
                    <option value="">{{ __('admin.filters.all') }} · {{ __('admin.fields.reason') }}</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" class="form-control form-control-solid w-150px" aria-label="{{ __('admin.filters.from') }}" dir="ltr" />
                <input type="date" name="to" class="form-control form-control-solid w-150px" aria-label="{{ __('admin.filters.to') }}" dir="ltr" />
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_movements_table" :url="route('admin.inventory.movements')" filters="#geni_movements_filters" search="#geni_movements_search"
                :order="[[0, 'desc']]" :page-length="25"
                :columns="[
                    ['data' => 'created_at', 'title' => __('admin.common.created_at')],
                    ['data' => 'branch', 'title' => __('admin.fields.branch_id'), 'orderable' => false],
                    ['data' => 'variant', 'title' => __('admin.fields.sku'), 'orderable' => false],
                    ['data' => 'quantity_delta', 'title' => __('admin.fields.quantity')],
                    ['data' => 'on_hand_after', 'title' => __('admin.inventory.on_hand_after'), 'orderable' => false],
                    ['data' => 'reason', 'title' => __('admin.fields.reason'), 'orderable' => false],
                    ['data' => 'note', 'title' => __('admin.fields.note'), 'orderable' => false],
                    ['data' => 'author', 'title' => __('admin.audit.columns.user'), 'orderable' => false],
                ]" />
        </div>
    </div>
@endsection
