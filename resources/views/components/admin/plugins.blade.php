{{-- Load page-level Metronic plugins declared in config('admin.plugins'), once per page. --}}
@props(['use' => []])
@php($rtl = app(\App\Support\Locale::class)->isRtl())
@foreach ((array) $use as $plugin)
    @php($definition = config("admin.plugins.{$plugin}"))
    @continue(! $definition)
    @once("geni-plugin-{$plugin}")
        @push('vendor-styles')
            @foreach (($rtl && ! empty($definition['css_rtl'])) ? $definition['css_rtl'] : ($definition['css'] ?? []) as $css)
                <link href="{{ asset($css) }}" rel="stylesheet" type="text/css" />
            @endforeach
        @endpush
        @push('vendor-scripts')
            @foreach ($definition['js'] ?? [] as $js)
                <script src="{{ asset($js) }}"></script>
            @endforeach
        @endpush
    @endonce
@endforeach
