@extends('admin.layouts.app')

@section('title', __('admin.users.create_title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.users.create_title')" :breadcrumbs="[__('admin.users.title') => route('admin.users.index'), __('admin.users.create_title') => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @include('admin.users._form', ['canAssignRoles' => true])
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="route('admin.users.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
