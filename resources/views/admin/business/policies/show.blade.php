@extends('admin.layouts.app')

@section('title', $policy->displayName())

@section('toolbar')
    <x-admin.toolbar :title="$policy->displayName()" :breadcrumbs="[__('admin.policies.title') => route('admin.policies.index'), $policy->displayName() => null]">
        @can('approve', $policy)
            <button type="button" class="btn btn-sm btn-light-success" data-geni-action="{{ route('admin.policies.approve', $policy) }}" data-geni-method="POST" data-geni-reload="true"
                    data-geni-confirm="{{ __('admin.policies.confirm_approve') }}"><i class="ki-outline ki-check-circle fs-5"></i>{{ __('admin.policies.approve') }}</button>
        @endcan
        @can('retire', $policy)
            <button type="button" class="btn btn-sm btn-light-warning" data-geni-action="{{ route('admin.policies.retire', $policy) }}" data-geni-method="POST" data-geni-reload="true"
                    data-geni-confirm="{{ __('admin.policies.confirm_retire') }}"><i class="ki-outline ki-archive fs-5"></i>{{ __('admin.policies.retire') }}</button>
        @endcan
        @can('update', $policy)
            <x-admin.button :href="route('admin.policies.edit', $policy)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $policy)
            <button type="button" class="btn btn-sm btn-light-danger" data-geni-action="{{ route('admin.policies.destroy', $policy) }}" data-geni-method="DELETE"
                    data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i>{{ __('admin.common.delete') }}</button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        <div class="col-xl-5">
            <x-admin.card :title="__('admin.policies.details')" icon="ki-document" class="mb-5">
                @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $policy->status])->render()])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.policy_code'), 'value' => e($policy->policy_code), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.version'), 'value' => 'v'.(int) $policy->version])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.organization_id'), 'value' => e($policy->organization->name)])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.effective_from'), 'value' => e(format_datetime($policy->effective_from))])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.effective_to'), 'value' => $policy->effective_to ? e(format_datetime($policy->effective_to)) : null])
                @include('admin.business.partials.detail', ['label' => __('admin.policies.created_by'), 'value' => $policy->author ? e($policy->author->name) : null])
                @include('admin.business.partials.detail', ['label' => __('admin.policies.approved_by'), 'value' => $policy->approver ? e($policy->approver->name.' · '.format_datetime($policy->approved_at)) : null])
            </x-admin.card>
            @if ($policy->status === \App\Enums\PolicyStatus::Draft)
                <x-admin.alert type="info">{{ __('admin.policies.approval_notice') }}</x-admin.alert>
            @else
                <x-admin.alert type="warning" icon="ki-lock">{{ __('admin.policies.immutable_notice') }}</x-admin.alert>
            @endif
        </div>
        <div class="col-xl-7">
            <x-admin.card :title="__('admin.fields.rule_body')" icon="ki-code">
                <pre class="bg-light rounded p-5 fs-7 mb-0" dir="ltr" style="white-space: pre-wrap">{{ json_encode($policy->rule_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </x-admin.card>
        </div>
    </div>
@endsection
