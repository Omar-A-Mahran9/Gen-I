<!DOCTYPE html>
{{-- Error layout: no session, CSRF or Vite dependencies, so it renders even when the request never reached the web middleware. --}}
<html lang="{{ $currentLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>@yield('title') · {{ $brand['name'] }}</title>
    <link rel="icon" href="{{ $brand['favicon'] }}" />
    @if ($isRtl)
        <link href="{{ asset('assets/admin/plugins/global/plugins.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('assets/admin/css/style.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    @else
        <link href="{{ asset('assets/admin/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('assets/admin/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    @endif
    <script>
        (function () {
            var mode;
            try { mode = localStorage.getItem('data-bs-theme-mode'); } catch (e) { mode = null; }
            mode = mode || @js($defaultTheme);
            if (mode === 'system') { mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', mode);
        })();
    </script>
</head>
<body id="kt_body" class="app-blank">
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <div class="d-flex flex-column flex-center flex-column-fluid">
            <div class="d-flex flex-column flex-center text-center p-10">
                <div class="card card-flush w-lg-650px py-5">
                    <div class="card-body py-15 py-lg-20">
                        <div class="mb-10">
                            <img src="{{ $brand['logo'] }}" class="h-40px theme-light-show" alt="{{ $brand['name'] }}" />
                            <img src="{{ $brand['logo_dark'] }}" class="h-40px theme-dark-show" alt="{{ $brand['name'] }}" />
                        </div>
                        <h1 class="fw-bolder fs-2hx text-gray-900 mb-4">@yield('heading')</h1>
                        <div class="fw-semibold fs-6 text-gray-500 mb-7">@yield('message')</div>
                        <div class="mb-11">
                            <img src="{{ asset('assets/admin/media/auth/'.trim($__env->yieldContent('illustration', '404')).'-error.png') }}" class="mw-100 mh-200px theme-light-show" alt="" />
                            <img src="{{ asset('assets/admin/media/auth/'.trim($__env->yieldContent('illustration', '404')).'-error-dark.png') }}" class="mw-100 mh-200px theme-dark-show" alt="" />
                        </div>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-primary">{{ __('admin.errors.back_home') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
