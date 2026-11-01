{{-- Page title, breadcrumbs and optional actions slot. --}}
@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div id="kt_app_toolbar" class="app-toolbar pt-7 pt-lg-10">
    <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex align-items-stretch">
        <div class="app-toolbar-wrapper d-flex flex-stack flex-wrap gap-4 w-100">
            <div class="page-title d-flex flex-column justify-content-center gap-1 me-3">
                <h1 class="page-heading d-flex flex-column justify-content-center text-gray-900 fw-bold fs-3 m-0">{{ $title }}</h1>
                @if ($breadcrumbs !== [])
                <nav aria-label="breadcrumb">
                    <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
                        <li class="breadcrumb-item text-muted">
                            <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">{{ __('admin.menu.dashboard') }}</a>
                        </li>
                        @foreach ($breadcrumbs as $label => $url)
                            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                            @if (is_string($url) && ! $loop->last)
                                <li class="breadcrumb-item text-muted"><a href="{{ $url }}" class="text-muted text-hover-primary">{{ $label }}</a></li>
                            @else
                                <li class="breadcrumb-item text-gray-700" aria-current="page">{{ is_string($label) ? $label : $url }}</li>
                            @endif
                        @endforeach
                    </ul>
                </nav>
                @endif
                @if ($subtitle)
                    <div class="text-gray-500 fs-7">{{ $subtitle }}</div>
                @endif
            </div>
            @if (trim($slot) !== '')
                <div class="d-flex align-items-center flex-wrap gap-2 gap-lg-3">{{ $slot }}</div>
            @endif
        </div>
    </div>
</div>
