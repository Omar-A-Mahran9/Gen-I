<div id="kt_app_header" class="app-header" data-kt-sticky="true" data-kt-sticky-activate="{default: true, lg: true}"
     data-kt-sticky-name="app-header-minimize" data-kt-sticky-animation="false" data-kt-sticky-offset="{default: '0px', lg: '0px'}">
    <div class="app-container container-fluid d-flex align-items-stretch flex-stack mt-lg-8" id="kt_app_header_container">

        {{-- Mobile: sidebar toggle + logo --}}
        <div class="d-flex align-items-center d-lg-none ms-n3 me-2">
            <button type="button" class="btn btn-icon btn-active-color-primary w-35px h-35px me-1" id="kt_app_sidebar_mobile_toggle"
                    aria-label="{{ __('admin.common.toggle_sidebar') }}">
                <i class="ki-outline ki-abstract-14 fs-2"></i>
            </button>
            <a href="{{ route('admin.dashboard') }}">
                <img alt="{{ $brand['name'] }}" src="{{ $brand['logo'] }}" class="h-30px theme-light-show" />
                <img alt="{{ $brand['name'] }}" src="{{ $brand['logo_dark'] }}" class="h-30px theme-dark-show" />
            </a>
        </div>

        <div class="app-navbar flex-lg-grow-1 justify-content-end" id="kt_app_header_navbar">
            <div class="app-navbar-item d-none d-lg-flex align-items-center flex-lg-grow-1">
                <span class="text-gray-500 fw-semibold fs-6">{{ __('admin.app.tagline') }}</span>
            </div>

            {{-- Language --}}
            <div class="app-navbar-item ms-1 ms-md-3">
                @include('admin.partials.locale-switcher')
            </div>

            {{-- Theme mode (handled by Metronic KTThemeMode; persisted in localStorage) --}}
            <div class="app-navbar-item ms-1 ms-md-3">
                <button type="button" class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end"
                        title="{{ __('admin.common.theme') }}" aria-label="{{ __('admin.common.theme') }}" data-geni-theme-toggle>
                    <i class="ki-outline ki-night-day theme-light-show fs-1"></i>
                    <i class="ki-outline ki-moon theme-dark-show fs-1"></i>
                </button>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
                     data-kt-menu="true" data-kt-element="theme-mode-menu">
                    @foreach (['light' => 'ki-night-day', 'dark' => 'ki-moon', 'system' => 'ki-screen'] as $mode => $icon)
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="{{ $mode }}">
                                <span class="menu-icon" data-kt-element="icon"><i class="ki-outline {{ $icon }} fs-2"></i></span>
                                <span class="menu-title">{{ __('admin.common.'.$mode) }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Notifications --}}
            <div class="app-navbar-item ms-1 ms-md-3">
                @include('admin.notifications.partials.dropdown')
            </div>

            {{-- User menu --}}
            <div class="app-navbar-item ms-1 ms-md-3">
                <button type="button" class="btn p-0 border-0 bg-transparent cursor-pointer symbol symbol-35px"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end"
                        aria-label="{{ $authUser->name }}" data-geni-user-menu>
                    <span class="symbol-label fs-6 fw-bold bg-light-primary text-primary">{{ $authUser->initials() }}</span>
                </button>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                    <div class="menu-item px-3">
                        <div class="menu-content d-flex align-items-center px-3">
                            <div class="symbol symbol-50px me-5">
                                <span class="symbol-label fs-3 fw-bold bg-light-primary text-primary">{{ $authUser->initials() }}</span>
                            </div>
                            <div class="d-flex flex-column min-w-0">
                                <div class="fw-bold d-flex align-items-center fs-5 text-truncate">{{ $authUser->name }}</div>
                                <span class="fw-semibold text-muted fs-7 text-truncate" dir="ltr">{{ $authUser->email }}</span>
                                <div class="mt-1">
                                    @foreach ($authUser->getRoleNames() as $roleName)
                                        <x-admin.role-badge :name="$roleName" />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="separator my-2"></div>
                    <div class="menu-item px-5">
                        <a href="{{ route('admin.profile.edit') }}" class="menu-link px-5">
                            <span class="menu-icon"><i class="ki-outline ki-user fs-3"></i></span>
                            <span class="menu-title">{{ __('admin.menu.profile') }}</span>
                        </a>
                    </div>
                    <div class="menu-item px-5">
                        <a href="{{ route('admin.notifications.index') }}" class="menu-link px-5">
                            <span class="menu-icon"><i class="ki-outline ki-notification-on fs-3"></i></span>
                            <span class="menu-title">{{ __('admin.menu.notifications') }}</span>
                            @if ($unreadNotificationsCount > 0)
                                <span class="menu-badge"><span class="badge badge-light-danger badge-circle fw-bold fs-7">{{ $unreadNotificationsCount }}</span></span>
                            @endif
                        </a>
                    </div>
                    <div class="separator my-2"></div>
                    <div class="menu-item px-5">
                        <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="menu-link px-5 w-100 border-0 bg-transparent text-start" data-geni-logout>
                                <span class="menu-icon"><i class="ki-outline ki-exit-right fs-3"></i></span>
                                <span class="menu-title">{{ __('admin.common.sign_out') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
