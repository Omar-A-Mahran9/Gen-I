@extends('admin.layouts.app')

@php($isEdit = $category->exists)
@section('title', $isEdit ? __('admin.categories.edit') : __('admin.categories.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.categories.edit') : __('admin.categories.add')"
        :breadcrumbs="[__('admin.categories.title') => route('admin.categories.index'), ($isEdit ? $category->displayName() : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <x-admin.card :title="__('admin.categories.details')" icon="ki-category" class="mb-5">
            @include('admin.business.partials.organization-field', ['model' => $category])
            <div class="row">
                <div class="col-md-4"><x-admin.form.input name="code" :label="__('admin.fields.code')" :value="$category->code" required maxlength="50" dir="ltr" :hint="__('admin.categories.code_hint')" /></div>
                <div class="col-md-8">
                    <x-admin.form.select name="parent_id" :label="__('admin.fields.parent_id')" :value="$category->parent_id" :placeholder="__('admin.categories.root')"
                        :options="$parents->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()" :hint="__('admin.categories.parent_hint')" />
                </div>
            </div>
            <div class="row">
                <div class="col-md-6"><x-admin.form.input name="name_ar" :label="__('admin.fields.name_ar')" :value="$category->name_ar" maxlength="120" dir="rtl" /></div>
                <div class="col-md-6"><x-admin.form.input name="name_en" :label="__('admin.fields.name_en')" :value="$category->name_en" required maxlength="120" dir="ltr" /></div>
            </div>
        </x-admin.card>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.categories.show', $category) : route('admin.categories.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
