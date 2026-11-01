@extends('admin.layouts.app')

@section('title', __('admin.inventory.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.inventory.title')" :subtitle="__('admin.inventory.subtitle')" :breadcrumbs="[__('admin.menu.inventory') => null, __('admin.menu.stock') => null]">
        @can('inventory.adjust')
            <button type="button" class="btn btn-sm btn-primary" data-geni-adjust><i class="ki-outline ki-plus fs-5"></i>{{ __('admin.inventory.new_adjustment') }}</button>
        @endcan
        <x-admin.button :href="route('admin.inventory.movements')" icon="ki-time" size="sm" variant="light">{{ __('admin.menu.movements') }}</x-admin.button>
    </x-admin.toolbar>
@endsection

@section('content')
    <x-admin.alert type="info" class="mb-5">{{ __('admin.inventory.source_notice') }}</x-admin.alert>
    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_inventory_filters" search-id="geni_inventory_search" :placeholder="__('admin.inventory.search')">
                <select name="branch_id" class="form-select form-select-solid w-200px" aria-label="{{ __('admin.fields.branch_id') }}">
                    <option value="">{{ __('admin.filters.all') }} · {{ __('admin.fields.branch_id') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) request('branch') === (string) $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
                <select name="stock" class="form-select form-select-solid w-175px" aria-label="{{ __('admin.inventory.stock_filter') }}">
                    <option value="">{{ __('admin.filters.all') }}</option>
                    <option value="available">{{ __('admin.inventory.in_stock') }}</option>
                    <option value="out">{{ __('admin.inventory.out_of_stock') }}</option>
                </select>
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_inventory_table" :url="route('admin.inventory.index')" filters="#geni_inventory_filters" search="#geni_inventory_search"
                :order="[[5, 'desc']]"
                :columns="[
                    ['data' => 'branch', 'title' => __('admin.fields.branch_id'), 'orderable' => false],
                    ['data' => 'variant', 'title' => __('admin.fields.variant_id'), 'orderable' => false, 'class' => 'min-w-200px'],
                    ['data' => 'on_hand', 'title' => __('admin.fields.on_hand')],
                    ['data' => 'reserved', 'title' => __('admin.fields.reserved')],
                    ['data' => 'available', 'title' => __('admin.inventory.available'), 'orderable' => false],
                    ['data' => 'updated_at', 'title' => __('admin.common.updated_at')],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'class' => 'text-end'],
                ]" />
        </div>
    </div>

    @can('inventory.adjust')
        @push('modals')
            <x-admin.modal id="geni_adjust_modal" :title="__('admin.inventory.new_adjustment')">
                <form method="POST" action="{{ route('admin.inventory.adjust') }}" data-geni-ajax-form data-geni-close-modal="#geni_adjust_modal" data-geni-table="#geni_inventory_table" novalidate>
                    @csrf
                    <input type="hidden" name="idempotency_key" value="" data-geni-idempotency />
                    <div class="text-gray-700 fw-semibold mb-5" data-geni-adjust-label></div>
                    <x-admin.form.select name="branch_id" id="adjust_branch_input" :label="__('admin.fields.branch_id')" required :placeholder="__('admin.common.select_option')"
                        :options="$branches->where('status', \App\Enums\ActiveStatus::Active)->mapWithKeys(fn ($b) => [$b->id => $b->code.' — '.$b->name])->all()" />
                    <x-admin.form.select name="variant_id" id="adjust_variant_input" :label="__('admin.fields.variant_id')" required :placeholder="__('admin.common.select_option')"
                        :options="$variants->mapWithKeys(fn ($v) => [$v->id => $v->sku.' — '.$v->product?->displayName().' · '.$v->variant_name])->all()" />
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.select name="reason" :label="__('admin.fields.reason')" required :options="$manualReasons" /></div>
                        <div class="col-md-6"><x-admin.form.input name="quantity" type="number" step="1" :label="__('admin.fields.quantity')" required dir="ltr" :hint="__('admin.inventory.quantity_hint')" /></div>
                    </div>
                    <x-admin.form.textarea name="note" :label="__('admin.fields.note')" rows="2" :hint="__('admin.inventory.note_hint')" />
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.inventory.record') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>
        @endpush
    @endcan
@endsection
