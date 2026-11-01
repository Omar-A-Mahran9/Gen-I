<button type="button" class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative"
        data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end"
        title="{{ __('admin.notifications.title') }}" aria-label="{{ __('admin.notifications.title') }}" data-geni-notifications-toggle>
    <i class="ki-outline ki-notification-status fs-1"></i>
    @if ($unreadNotificationsCount > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-danger w-18px h-18px fs-9" data-geni-unread-badge>
            {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
        </span>
    @endif
</button>
<div class="menu menu-sub menu-sub-dropdown menu-column w-325px w-lg-375px" data-kt-menu="true">
    <div class="d-flex flex-stack bg-primary rounded-top px-8 py-6">
        <h3 class="text-white fw-semibold fs-4 mb-0">{{ __('admin.notifications.title') }}</h3>
        @if ($unreadNotificationsCount > 0)
            <span class="badge badge-light-primary fs-8">{{ __('admin.notifications.new', ['count' => $unreadNotificationsCount]) }}</span>
        @endif
    </div>
    <div class="scroll-y mh-325px my-3 px-8">
        @forelse ($headerNotifications as $notification)
            @include('admin.notifications.partials.item', ['notification' => $notification, 'showActions' => false])
            @unless ($loop->last)<div class="separator separator-dashed"></div>@endunless
        @empty
            <div class="text-center text-gray-500 py-10">
                <i class="ki-outline ki-notification-bing fs-3x mb-3 d-block"></i>
                {{ __('admin.notifications.empty') }}
            </div>
        @endforelse
    </div>
    <div class="d-flex flex-stack border-top px-6 py-3">
        <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-color-gray-700 btn-active-color-primary">
            {{ __('admin.notifications.view_all') }}
            <i class="ki-outline ki-arrow-{{ $isRtl ? 'left' : 'right' }} fs-5"></i>
        </a>
        @if ($unreadNotificationsCount > 0)
            <button type="button" class="btn btn-sm btn-light-primary"
                    data-geni-action="{{ route('admin.notifications.read-all') }}" data-geni-method="POST" data-geni-reload="true">
                {{ __('admin.notifications.mark_all_read') }}
            </button>
        @endif
    </div>
</div>
