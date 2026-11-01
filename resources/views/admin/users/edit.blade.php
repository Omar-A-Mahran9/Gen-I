@extends('admin.layouts.app')

@section('title', __('admin.users.edit_title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.users.edit_title')" :breadcrumbs="[__('admin.users.title') => route('admin.users.index'), $user->name => route('admin.users.show', $user), __('admin.common.edit') => null]">
        <x-admin.button :href="route('admin.users.show', $user)" variant="light" size="sm" icon="ki-eye">{{ __('admin.common.view') }}</x-admin.button>
    </x-admin.toolbar>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}" data-geni-ajax-form novalidate>
        @csrf
        @method('PUT')
        @include('admin.users._form')
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="route('admin.users.show', $user)" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
        </div>
    </form>
@endsection
