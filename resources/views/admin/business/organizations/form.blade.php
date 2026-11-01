@extends('admin.layouts.app')

@php($isEdit = $organization->exists)
@section('title', $isEdit ? __('admin.organizations.edit') : __('admin.organizations.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.organizations.edit') : __('admin.organizations.add')"
        :breadcrumbs="[__('admin.organizations.title') => route('admin.organizations.index'), ($isEdit ? $organization->name : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.organizations.update', $organization) : route('admin.organizations.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <x-admin.card :title="__('admin.organizations.details')" icon="ki-office-bag" class="mb-5">
            <x-admin.form.input name="name" :label="__('admin.fields.name')" :value="$organization->name" required maxlength="160" />
            <div class="row">
                <div class="col-md-6">
                    <x-admin.form.select name="timezone" :label="__('admin.fields.timezone')" :value="$organization->timezone" required select2
                        :options="array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())" :hint="__('admin.organizations.timezone_hint')" />
                </div>
                <div class="col-md-6">
                    <x-admin.form.input name="currency" :label="__('admin.fields.currency')" :value="$organization->currency" required maxlength="3" dir="ltr" :hint="__('admin.organizations.currency_hint')" />
                </div>
            </div>
        </x-admin.card>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.organizations.show', $organization) : route('admin.organizations.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
