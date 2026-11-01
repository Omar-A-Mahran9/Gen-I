@extends('admin.layouts.app')

@section('title', __('admin.roles.create_title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.roles.create_title')" :breadcrumbs="[__('admin.roles.title') => route('admin.roles.index'), __('admin.roles.create_title') => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.roles.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @include('admin.roles._form')
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="route('admin.roles.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
