{{-- Shared scripts for every admin page. Page plugins are pushed by <x-admin.plugins>. --}}
<script>
    var hostUrl = @js(asset('assets/admin').'/');
    window.GENI = window.GENI || {};
    window.GENI.config = {
        locale: @js($currentLocale),
        rtl: @js($isRtl),
        csrfToken: @js(csrf_token()),
        i18n: @js(array_merge(trans('admin.js'), ['datatable' => trans('admin.datatable')])),
        flash: @js(array_filter([
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
        ])),
    };
</script>

{{-- Metronic 8.2.0 global JavaScript bundles (jQuery, Bootstrap, KT components). --}}
<script src="{{ asset('assets/admin/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ asset('assets/admin/js/scripts.bundle.js') }}"></script>

@stack('vendor-scripts')

{{-- GEN-I admin utilities (AJAX forms, confirmations, toasts, DataTables, Select2 AJAX). --}}
@vite('resources/js/admin/app.js')

@stack('scripts')
