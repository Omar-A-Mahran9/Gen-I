@extends('admin.layouts.app')

@section('title', __('admin.notifications.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.notifications.title')" :breadcrumbs="[__('admin.notifications.title') => null]">
        @if ($unreadCount > 0)
            <button type="button" class="btn btn-sm btn-light-primary"
                    data-geni-action="{{ route('admin.notifications.read-all') }}" data-geni-method="POST" data-geni-reload="true">
                <i class="ki-outline ki-check-square fs-4"></i>{{ __('admin.notifications.mark_all_read') }}
            </button>
        @endif
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bold">
                <li class="nav-item">
                    <a class="nav-link {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">{{ __('admin.notifications.filters.all') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $filter === 'unread' ? 'active' : '' }}" href="{{ route('admin.notifications.index', ['filter' => 'unread']) }}">
                        {{ __('admin.notifications.filters.unread') }}
                        @if ($unreadCount > 0)<span class="badge badge-light-danger ms-2">{{ $unreadCount }}</span>@endif
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            @forelse ($notifications as $notification)
                @include('admin.notifications.partials.item', ['notification' => $notification, 'showActions' => true])
                @unless ($loop->last)<div class="separator separator-dashed"></div>@endunless
            @empty
                <x-admin.empty-state :title="$filter === 'unread' ? __('admin.notifications.empty_unread') : __('admin.notifications.empty')" />
            @endforelse

            @if ($notifications->hasPages())
                <div class="d-flex justify-content-center mt-8">{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
@endsection
