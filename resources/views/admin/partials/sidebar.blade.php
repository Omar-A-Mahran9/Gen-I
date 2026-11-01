{{-- Sidebar rendered from config('admin.menu'), filtered by permission (App\Support\AdminMenu). --}}
<div id="kt_app_sidebar" class="app-sidebar flex-column mt-lg-4 ps-2 pe-2 ps-lg-7 pe-lg-4"
     data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}"
     data-kt-drawer-overlay="true" data-kt-drawer-width="250px" data-kt-drawer-direction="start"
     data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <div class="app-sidebar-logo flex-shrink-0 d-none d-lg-flex flex-center align-items-center" id="kt_app_sidebar_logo">
        <a href="{{ route('admin.dashboard') }}">
            <img alt="{{ $brand['name'] }}" src="{{ $brand['logo'] }}" class="h-40px theme-light-show" />
            <img alt="{{ $brand['name'] }}" src="{{ $brand['logo_dark'] }}" class="h-40px theme-dark-show" />
        </a>
    </div>

    <div class="app-sidebar-menu flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="hover-scroll-overlay-y my-5" data-kt-scroll="true"
             data-kt-scroll-activate="true" data-kt-scroll-height="auto"
             data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
             data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px">
            <nav class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6 px-3 bg-transparent"
                 id="kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false" aria-label="{{ __('admin.menu.dashboard') }}">
                @foreach ($menu as $item)
                    @if (isset($item['heading']))
                        <div class="menu-item pt-5">
                            <div class="menu-content">
                                <span class="menu-heading fw-bold text-uppercase fs-7 text-gray-500">{{ __($item['heading']) }}</span>
                            </div>
                        </div>
                    @elseif (! empty($item['children']))
                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion {{ $item['active'] ? 'here show' : '' }}">
                            <span class="menu-link">
                                <span class="menu-icon"><i class="{{ $item['icon'] ?? 'ki-outline ki-abstract-26' }} fs-2"></i></span>
                                <span class="menu-title">{{ __($item['label']) }}</span>
                                <span class="menu-arrow"></span>
                            </span>
                            <div class="menu-sub menu-sub-accordion">
                                @foreach ($item['children'] as $child)
                                    <div class="menu-item">
                                        <a class="menu-link {{ $child['active'] ? 'active' : '' }}" href="{{ $child['url'] }}"
                                           @if ($child['active']) aria-current="page" @endif>
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">{{ __($child['label']) }}</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="menu-item">
                            <a class="menu-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}"
                               @if ($item['active']) aria-current="page" @endif>
                                <span class="menu-icon"><i class="{{ $item['icon'] ?? 'ki-outline ki-abstract-26' }} fs-2"></i></span>
                                <span class="menu-title">{{ __($item['label']) }}</span>
                                @if ($item['key'] === 'notifications' && $unreadNotificationsCount > 0)
                                    <span class="menu-badge"><span class="badge badge-light-danger fw-bold">{{ $unreadNotificationsCount }}</span></span>
                                @endif
                            </a>
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>

    <div class="app-sidebar-footer d-flex align-items-center px-5 pb-8 pt-3" id="kt_app_sidebar_footer">
        <div class="d-flex align-items-center min-w-0">
            <div class="symbol symbol-40px me-3">
                <span class="symbol-label fs-6 fw-bold bg-light-primary text-primary">{{ $authUser->initials() }}</span>
            </div>
            <div class="d-flex flex-column min-w-0">
                <span class="text-gray-500 fs-8 fw-semibold">{{ __('admin.common.hello') }}</span>
                <a href="{{ route('admin.profile.edit') }}" class="text-gray-800 fs-7 fw-bold text-hover-primary text-truncate">{{ $authUser->name }}</a>
            </div>
        </div>
    </div>
</div>
