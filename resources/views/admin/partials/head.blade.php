{{-- Shared <head> content for every admin page (app, auth and error layouts). --}}
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="robots" content="noindex, nofollow" />
<meta name="description" content="{{ $brand['name'] }} — {{ __('admin.app.tagline') }}" />
<title>@hasSection('title')@yield('title') · @endif{{ $brand['name'] }}</title>
<link rel="icon" href="{{ $brand['favicon'] }}" />

{{-- Locale font strategy (config/admin.php → fonts). Self-hosted, no CDN. --}}
@if (! empty($localeFont['family']))
    <style>
        @foreach ($localeFont['faces'] as $weight => $path)
        @font-face { font-family: "{{ $localeFont['family'] }}"; src: url("{{ asset($path) }}") format("truetype"); font-weight: {{ $weight }}; font-style: normal; font-display: swap; }
        @endforeach
        html[lang="{{ $currentLocale }}"] {
            --bs-font-sans-serif: "{{ $localeFont['family'] }}", Inter, Helvetica, Arial, sans-serif;
            --bs-body-font-family: "{{ $localeFont['family'] }}", Inter, Helvetica, Arial, sans-serif;
        }
        /* Metronic hard-codes "html, body { font-family: Inter… }"; icon fonts keep their own family. */
        html[lang="{{ $currentLocale }}"],
        html[lang="{{ $currentLocale }}"] body {
            font-family: var(--bs-body-font-family);
        }
    </style>
@endif

{{-- Metronic 8.2.0 global bundles (direction-aware). --}}
@if ($isRtl)
    <link href="{{ asset('assets/admin/plugins/global/plugins.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/admin/css/style.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
@else
    <link href="{{ asset('assets/admin/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/admin/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
@endif

{{-- Page-level vendor styles (pushed by <x-admin.plugins>). --}}
@stack('vendor-styles')

{{-- GEN-I overrides (Vite entry without Tailwind). --}}
@vite('resources/css/admin.css')

@stack('styles')

{{-- Apply the saved/default theme before first paint to avoid a flash. --}}
<script>
    var defaultThemeMode = @js($defaultTheme);
    (function () {
        var mode;
        try { mode = localStorage.getItem('data-bs-theme-mode'); } catch (e) { mode = null; }
        mode = mode || defaultThemeMode || 'light';
        if (mode === 'system') {
            mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-bs-theme', mode);
    })();
</script>
