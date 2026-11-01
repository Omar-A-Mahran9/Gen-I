@extends('admin.layouts.app')

@section('title', __('admin.audit.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.audit.title')" :subtitle="__('admin.audit.subtitle')" :breadcrumbs="[__('admin.audit.title') => null]" />
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <form id="geni_audit_filters" class="card-title flex-wrap gap-3 w-100" onsubmit="return false">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5"></i>
                    <input type="search" id="geni_audit_search" class="form-control form-control-solid w-225px ps-13" placeholder="{{ __('admin.common.search') }}" aria-label="{{ __('admin.common.search') }}" />
                </div>
                <select name="event" class="form-select form-select-solid w-225px" aria-label="{{ __('admin.audit.filters.event') }}">
                    <option value="">{{ __('admin.audit.filters.all_events') }}</option>
                    @foreach ($events as $event)
                        @php($key = 'admin.audit.events.'.str_replace('.', '_', $event))
                        <option value="{{ $event }}">{{ \Illuminate\Support\Facades\Lang::has($key) ? __($key) : $event }}</option>
                    @endforeach
                </select>
                @can('users.view')
                    <div class="w-250px">
                        <select name="user_id" class="form-select form-select-solid" data-control="select2" data-allow-clear="true"
                                data-placeholder="{{ __('admin.audit.filters.any_user') }}"
                                data-geni-select2-ajax="{{ route('admin.users.search') }}" aria-label="{{ __('admin.audit.filters.user') }}">
                            <option value=""></option>
                        </select>
                    </div>
                @endcan
            </form>
        </div>
        <div class="card-body pt-0">
            <x-admin.datatable id="geni_audit_table" :url="route('admin.audit-logs.index')" filters="#geni_audit_filters" search="#geni_audit_search"
                :order="[[0, 'desc']]" :page-length="25"
                :columns="[
                    ['data' => 'created_at', 'title' => __('admin.audit.columns.date'), 'class' => 'min-w-150px'],
                    ['data' => 'user', 'title' => __('admin.audit.columns.user'), 'orderable' => false],
                    ['data' => 'event', 'title' => __('admin.audit.columns.event')],
                    ['data' => 'subject', 'title' => __('admin.audit.columns.subject'), 'orderable' => false],
                    ['data' => 'details', 'title' => __('admin.audit.columns.details'), 'orderable' => false, 'class' => 'min-w-200px'],
                    ['data' => 'ip_address', 'title' => __('admin.audit.columns.ip'), 'orderable' => false],
                ]" />
        </div>
    </div>
@endsection
