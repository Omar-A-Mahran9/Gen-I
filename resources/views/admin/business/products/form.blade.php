@extends('admin.layouts.app')

@php($isEdit = $product->exists)
@section('title', $isEdit ? __('admin.products.edit') : __('admin.products.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.products.edit') : __('admin.products.add')"
        :breadcrumbs="[__('admin.products.title') => route('admin.products.index'), ($isEdit ? $product->displayName() : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <div class="row g-5 g-xl-8">
            <div class="col-xl-7">
                <x-admin.card :title="__('admin.products.details')" icon="ki-handcart" class="mb-5">
                    @include('admin.business.partials.organization-field', ['model' => $product])
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="name_ar" :label="__('admin.fields.name_ar')" :value="$product->name_ar" maxlength="200" dir="rtl" /></div>
                        <div class="col-md-6"><x-admin.form.input name="name_en" :label="__('admin.fields.name_en')" :value="$product->name_en" required maxlength="200" dir="ltr" /></div>
                    </div>
                    <x-admin.form.textarea name="description" :label="__('admin.fields.description')" :value="$product->description" rows="5" />
                </x-admin.card>
            </div>
            <div class="col-xl-5">
                <x-admin.card :title="__('admin.products.identity')" icon="ki-barcode" class="mb-5">
                    <x-admin.form.select name="category_id" :label="__('admin.fields.category_id')" :value="$product->category_id" required select2 :placeholder="__('admin.common.select_option')"
                        :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()" />
                    <x-admin.form.select name="brand_id" :label="__('admin.fields.brand_id')" :value="$product->brand_id" required select2 :placeholder="__('admin.common.select_option')"
                        :options="$brands->pluck('name', 'id')->all()" />
                    <x-admin.form.input name="model_code" :label="__('admin.fields.model_code')" :value="$product->model_code" required maxlength="100" dir="ltr" :hint="__('admin.products.model_code_hint')" />
                    <x-admin.form.textarea name="source_reference" :label="__('admin.fields.source_reference')" :value="$product->source_reference" required rows="2" :hint="__('admin.products.source_hint')" />
                </x-admin.card>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.products.show', $product) : route('admin.products.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
