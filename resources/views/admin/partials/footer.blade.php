<div id="kt_app_footer" class="app-footer">
    <div class="app-container container-fluid d-flex flex-column flex-md-row flex-center flex-md-stack py-3">
        <div class="text-gray-600 order-2 order-md-1 fs-7">
            {{ __('admin.common.copyright', ['year' => now()->year, 'name' => $brand['name']]) }}
        </div>
        <div class="order-1 order-md-2 text-gray-500 fs-8">
            {{ __('admin.app.tagline') }}
        </div>
    </div>
</div>
