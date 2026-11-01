<!DOCTYPE html>
<html lang="{{ $currentLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" data-bs-theme-mode="{{ $defaultTheme }}">
<head>
    @include('admin.partials.head')
</head>
<body id="kt_app_body"
      data-kt-app-header-fixed="true"
      data-kt-app-header-fixed-mobile="true"
      data-kt-app-sidebar-enabled="true"
      data-kt-app-sidebar-fixed="true"
      data-kt-app-sidebar-hoverable="true"
      data-kt-app-sidebar-push-header="true"
      data-kt-app-sidebar-push-toolbar="true"
      data-kt-app-sidebar-push-footer="true"
      data-kt-app-toolbar-enabled="true"
      class="app-default">

    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">

            @include('admin.partials.header')

            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">

                @include('admin.partials.sidebar')

                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <div class="d-flex flex-column flex-column-fluid">

                        {{-- Toolbar: page title, breadcrumbs and page actions --}}
                        @yield('toolbar')

                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <div id="kt_app_content_container" class="app-container container-fluid">
                                @yield('content')
                            </div>
                        </div>
                    </div>

                    @include('admin.partials.footer')
                </div>
            </div>
        </div>
    </div>

    <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true" title="{{ __('admin.common.scroll_top') }}">
        <i class="ki-outline ki-arrow-up"></i>
    </div>

    @stack('modals')

    @include('admin.partials.scripts')
</body>
</html>
