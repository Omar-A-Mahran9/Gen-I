@extends('admin.layouts.app')

@section('title', $list->code)

@section('toolbar')
    <x-admin.toolbar :title="$list->code" :breadcrumbs="[__('admin.pricing.title') => route('admin.price-lists.index'), $list->code => null]">
        @can('update', $list)
            @include('admin.business.partials.status-actions', ['url' => route('admin.price-lists.status', $list), 'enum' => \App\Enums\ActiveStatus::class,
                'targets' => [$list->status === \App\Enums\ActiveStatus::Active ? 'inactive' : 'active']])
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#geni_price_modal"><i class="ki-outline ki-plus fs-5"></i>{{ __('admin.pricing.add_price') }}</button>
            <x-admin.button :href="route('admin.price-lists.edit', $list)" icon="ki-pencil" size="sm" variant="light">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-4"><x-admin.stat-card :label="__('admin.pricing.prices')" :value="$list->prices_count" icon="ki-price-tag" /></div>
        <div class="col-md-4">
            <div class="card card-flush h-100"><div class="card-body py-7">
                <div class="text-gray-500 fs-7">{{ __('admin.fields.currency') }}</div>
                <div class="fs-2hx fw-bold" dir="ltr">{{ $list->currency }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush h-100"><div class="card-body py-7">
                <div class="text-gray-500 fs-7 mb-2">{{ __('admin.fields.status') }}</div>
                @include('admin.business.partials.status-badge', ['status' => $list->status])
                <div class="text-muted fs-8 mt-2">{{ $list->organization->name }}</div>
            </div></div>
        </div>
    </div>

    <x-admin.alert type="info" class="mb-5">{{ __('admin.pricing.history_notice') }}</x-admin.alert>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <x-admin.filter-bar id="geni_prices_filters" search-id="geni_prices_search" :placeholder="__('admin.pricing.search_prices')">
                <select name="period" class="form-select form-select-solid w-175px" aria-label="{{ __('admin.pricing.period') }}">
                    <option value="">{{ __('admin.pricing.all_periods') }}</option>
                    @foreach (['current', 'future', 'past'] as $period)
                        <option value="{{ $period }}">{{ __('admin.pricing.state.'.$period) }}</option>
                    @endforeach
                </select>
            </x-admin.filter-bar>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_prices_table" :url="route('admin.price-lists.show', $list)" filters="#geni_prices_filters" search="#geni_prices_search"
                :order="[[4, 'desc']]"
                :columns="[
                    ['data' => 'variant', 'title' => __('admin.fields.variant_id'), 'orderable' => false, 'class' => 'min-w-200px'],
                    ['data' => 'min_quantity', 'title' => __('admin.fields.min_quantity')],
                    ['data' => 'unit_price', 'title' => __('admin.fields.unit_price')],
                    ['data' => 'tax_policy', 'title' => __('admin.fields.tax_policy_id'), 'orderable' => false],
                    ['data' => 'valid_from', 'title' => __('admin.fields.valid_from')],
                    ['data' => 'valid_to', 'title' => __('admin.fields.valid_to'), 'orderable' => false],
                    ['data' => 'state', 'title' => __('admin.pricing.period'), 'orderable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'class' => 'text-end'],
                ]" />
        </div>
    </div>

    @can('update', $list)
        @push('modals')
            <x-admin.modal id="geni_price_modal" :title="__('admin.pricing.add_price')">
                @if ($taxPolicies->isEmpty())
                    <x-admin.alert type="warning" class="mb-5">{{ __('admin.pricing.no_tax_policy') }}</x-admin.alert>
                @endif
                <form method="POST" action="{{ route('admin.price-lists.prices.store', $list) }}" data-geni-ajax-form data-geni-close-modal="#geni_price_modal" novalidate>
                    @csrf
                    <x-admin.form.select name="variant_id" :label="__('admin.fields.variant_id')" required select2 :placeholder="__('admin.common.select_option')"
                        :options="$variants->mapWithKeys(fn ($v) => [$v->id => $v->sku.' — '.$v->product->displayName().' · '.$v->variant_name])->all()" />
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="unit_price" type="number" step="0.01" min="0" :label="__('admin.fields.unit_price').' ('.$list->currency.')'" required dir="ltr" /></div>
                        <div class="col-md-6"><x-admin.form.input name="min_quantity" type="number" min="1" value="1" :label="__('admin.fields.min_quantity')" required /></div>
                    </div>
                    <x-admin.form.select name="tax_policy_id" :label="__('admin.fields.tax_policy_id')" required :placeholder="__('admin.common.select_option')"
                        :options="$taxPolicies->mapWithKeys(fn ($p) => [$p->id => $p->displayName()])->all()" :hint="__('admin.pricing.tax_hint')" />
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="valid_from" type="datetime-local" :label="__('admin.fields.valid_from')" dir="ltr" :hint="__('admin.pricing.valid_from_hint')" /></div>
                        <div class="col-md-6"><x-admin.form.input name="valid_to" type="datetime-local" :label="__('admin.fields.valid_to')" dir="ltr" /></div>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>
        @endpush
    @endcan
@endsection
