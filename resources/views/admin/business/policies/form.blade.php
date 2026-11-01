@extends('admin.layouts.app')

@php($isEdit = $policy->exists)
@section('title', $isEdit ? __('admin.policies.edit') : __('admin.policies.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.policies.edit') : __('admin.policies.add')"
        :breadcrumbs="[__('admin.policies.title') => route('admin.policies.index'), ($isEdit ? $policy->displayName() : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.policies.update', $policy) : route('admin.policies.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <div class="row g-5 g-xl-8">
            <div class="col-xl-5">
                <x-admin.card :title="__('admin.policies.details')" icon="ki-document" class="mb-5">
                    @include('admin.business.partials.organization-field', ['model' => $policy])
                    @if ($isEdit)
                        @include('admin.business.partials.detail', ['label' => __('admin.fields.policy_code'), 'value' => e($policy->displayName()), 'ltr' => true])
                        <div class="mb-7"></div>
                    @else
                        <x-admin.form.input name="policy_code" :label="__('admin.fields.policy_code')" required maxlength="60" dir="ltr" placeholder="tax.vat" :hint="__('admin.policies.code_hint')" />
                    @endif
                    <x-admin.form.input name="effective_from" type="datetime-local" :label="__('admin.fields.effective_from')" :value="$policy->effective_from?->format('Y-m-d\TH:i')" required dir="ltr" />
                    <x-admin.form.input name="effective_to" type="datetime-local" :label="__('admin.fields.effective_to')" :value="$policy->effective_to?->format('Y-m-d\TH:i')" dir="ltr" />
                    <x-admin.alert type="warning">{{ __('admin.policies.draft_notice') }}</x-admin.alert>
                </x-admin.card>
            </div>
            <div class="col-xl-7">
                <x-admin.card :title="__('admin.fields.rule_body')" icon="ki-code" class="mb-5" :subtitle="__('admin.policies.rule_body_hint')">
                    <x-admin.form.textarea name="rule_body" :label="__('admin.fields.rule_body')" required rows="14" dir="ltr" class="font-monospace"
                        :value="$policy->rule_body ? json_encode($policy->rule_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''" />
                </x-admin.card>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.policies.show', $policy) : route('admin.policies.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
