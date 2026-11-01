@extends('admin.layouts.app')

@php($isEdit = $list->exists)
@section('title', $isEdit ? __('admin.pricing.edit') : __('admin.pricing.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.pricing.edit') : __('admin.pricing.add')"
        :breadcrumbs="[__('admin.pricing.title') => route('admin.price-lists.index'), ($isEdit ? $list->code : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.price-lists.update', $list) : route('admin.price-lists.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <x-admin.card :title="__('admin.pricing.details')" icon="ki-price-tag" class="mb-5">
            @include('admin.business.partials.organization-field', ['model' => $list])
            <div class="row">
                <div class="col-md-8"><x-admin.form.input name="code" :label="__('admin.fields.code')" :value="$list->code" required maxlength="40" dir="ltr" :hint="__('admin.pricing.code_hint')" /></div>
                <div class="col-md-4"><x-admin.form.input name="currency" :label="__('admin.fields.currency')" :value="$list->currency" required maxlength="3" dir="ltr" :hint="__('admin.pricing.currency_hint')" /></div>
            </div>
            <x-admin.alert type="info">{{ __('admin.pricing.contract_notice') }}</x-admin.alert>
        </x-admin.card>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.price-lists.show', $list) : route('admin.price-lists.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
