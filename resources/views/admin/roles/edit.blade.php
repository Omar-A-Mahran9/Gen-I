@extends('admin.layouts.app')

@section('title', __('admin.roles.edit_title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.roles.edit_title')" :breadcrumbs="[__('admin.roles.title') => route('admin.roles.index'), $role->name => route('admin.roles.show', $role), __('admin.common.edit') => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.roles.update', $role) }}" data-geni-ajax-form novalidate>
        @csrf
        @method('PUT')
        @include('admin.roles._form')
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="route('admin.roles.show', $role)" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
        </div>
    </form>
@endsection
