{{-- Inline Metronic notice. type: primary|success|info|warning|danger --}}
@props(['type' => 'info', 'icon' => null, 'title' => null])
@php($icon ??= match ($type) { 'success' => 'ki-check-circle', 'warning' => 'ki-information-5', 'danger' => 'ki-shield-cross', default => 'ki-information' })
<div {{ $attributes->merge(['class' => "notice d-flex bg-light-{$type} rounded border-{$type} border border-dashed p-5"]) }} role="alert">
    <i class="ki-outline {{ $icon }} fs-2tx text-{{ $type }} me-4 flex-shrink-0"></i>
    <div class="d-flex flex-column justify-content-center">
        @if ($title)<h4 class="text-gray-900 fw-bold mb-1">{{ $title }}</h4>@endif
        <div class="fs-6 text-gray-700">{{ $slot }}</div>
    </div>
</div>
