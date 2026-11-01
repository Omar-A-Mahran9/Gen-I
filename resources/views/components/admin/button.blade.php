{{-- Button with Metronic progress indicator (shown while an AJAX form submits). --}}
@props(['type' => 'button', 'variant' => 'primary', 'icon' => null, 'href' => null, 'size' => null])
@php($classes = trim('btn btn-'.$variant.' '.($size ? 'btn-'.$size : '')))
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<i class="ki-outline {{ $icon }} fs-4"></i>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span class="indicator-label">
            @if ($icon)<i class="ki-outline {{ $icon }} fs-4"></i>@endif
            {{ $slot }}
        </span>
        <span class="indicator-progress">
            {{ __('admin.common.please_wait') }}
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
        </span>
    </button>
@endif
