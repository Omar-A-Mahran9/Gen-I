@extends('admin.layouts.app')

@php($isEdit = $company->exists)
@section('title', $isEdit ? __('admin.companies.edit') : __('admin.companies.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.companies.edit') : __('admin.companies.add')"
        :breadcrumbs="[__('admin.companies.title') => route('admin.companies.index'), ($isEdit ? $company->legal_name : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.companies.update', $company) : route('admin.companies.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <x-admin.card :title="__('admin.companies.details')" icon="ki-briefcase" class="mb-5">
            @include('admin.business.partials.organization-field', ['model' => $company])
            <x-admin.form.input name="legal_name" :label="__('admin.fields.legal_name')" :value="$company->legal_name" required maxlength="200" />
            <div class="row">
                <div class="col-md-6">
                    <x-admin.form.input name="tax_reference" :label="__('admin.fields.tax_reference')" :value="$company->tax_reference" maxlength="80" dir="ltr" :hint="__('admin.companies.tax_hint')" />
                </div>
                <div class="col-md-6">
                    <x-admin.form.select name="account_owner_id" :label="__('admin.fields.account_owner_id')" :value="$company->account_owner_id" select2
                        :placeholder="__('admin.common.none')"
                        :options="$owners->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all()" :hint="__('admin.companies.owner_hint')" />
                </div>
            </div>
            @unless ($isEdit)
                <x-admin.alert type="info">{{ __('admin.companies.pending_notice') }}</x-admin.alert>
            @endunless
        </x-admin.card>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.companies.show', $company) : route('admin.companies.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
