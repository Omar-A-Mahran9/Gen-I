@extends('admin.layouts.app')

@php($isEdit = $brand->exists)
@php($canEdit = ! $isEdit || auth()->user()->can('update', $brand))
@section('title', $isEdit ? $brand->name : __('admin.brands.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? $brand->name : __('admin.brands.add')" :breadcrumbs="[__('admin.brands.title') => route('admin.brands.index'), ($isEdit ? $brand->name : __('admin.common.create')) => null]">
        @if ($isEdit && $canEdit)
            @include('admin.business.partials.status-actions', ['url' => route('admin.brands.status', $brand), 'enum' => \App\Enums\ActiveStatus::class,
                'targets' => [$brand->status === \App\Enums\ActiveStatus::Active ? 'inactive' : 'active']])
        @endif
    </x-admin.toolbar>
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <fieldset @disabled(! $canEdit)>
            <x-admin.card :title="__('admin.brands.details')" icon="ki-award" class="mb-5">
                @include('admin.business.partials.organization-field', ['model' => $brand])
                <x-admin.form.input name="name" :label="__('admin.fields.name')" :value="$brand->name" required maxlength="100" :hint="__('admin.brands.name_hint')" />
                @if ($isEdit)
                    @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $brand->status])->render()])
                    @include('admin.business.partials.detail', ['label' => __('admin.organizations.stat.products'), 'value' => (string) $brand->products_count])
                @endif
            </x-admin.card>
        </fieldset>
        @if ($canEdit)
            <div class="d-flex justify-content-end gap-3">
                <x-admin.button :href="route('admin.brands.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
                <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
            </div>
        @endif
    </form>
@endsection
