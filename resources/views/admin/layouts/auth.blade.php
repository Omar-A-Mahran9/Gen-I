<!DOCTYPE html>
<html lang="{{ $currentLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" data-bs-theme-mode="{{ $defaultTheme }}">
<head>
    @include('admin.partials.head')
</head>
<body id="kt_body" class="app-blank">
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">

            {{-- Form side --}}
            <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <a href="{{ route('admin.login') }}" class="d-inline-block">
                        <img alt="{{ $brand['name'] }}" src="{{ $brand['logo'] }}" class="h-35px theme-light-show" />
                        <img alt="{{ $brand['name'] }}" src="{{ $brand['logo_dark'] }}" class="h-35px theme-dark-show" />
                    </a>
                    @include('admin.partials.locale-switcher', ['compact' => true])
                </div>

                <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                    <div class="w-100 w-lg-500px p-5 p-lg-10">
                        @yield('content')
                    </div>
                </div>

                <div class="d-flex flex-center flex-wrap px-5 text-muted fs-7">
                    {{ __('admin.common.copyright', ['year' => now()->year, 'name' => $brand['name']]) }}
                </div>
            </div>

            {{-- Visual side --}}
            <div class="d-none d-lg-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-1 order-lg-2 geni-auth-aside">
                <div class="d-flex flex-column flex-center py-15 px-5 px-md-15 w-100">
                    <img alt="{{ $brand['name'] }}" src="{{ $brand['logo_dark'] }}" class="h-60px mb-10" />
                    <h1 class="text-white fs-2qx fw-bolder text-center mb-5">{{ $brand['name'] }}</h1>
                    <div class="text-white-50 fs-4 text-center">{{ __('admin.app.tagline') }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.scripts')
</body>
</html>
