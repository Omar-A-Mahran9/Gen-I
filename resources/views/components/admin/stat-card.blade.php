@props(['label', 'value', 'icon', 'color' => 'primary', 'href' => null])
<div {{ $attributes->merge(['class' => 'card card-flush h-100']) }}>
    <div class="card-body d-flex align-items-center gap-5 py-7">
        <div class="symbol symbol-50px flex-shrink-0">
            <span class="symbol-label bg-light-{{ $color }}"><i class="ki-outline {{ $icon }} fs-2x text-{{ $color }}"></i></span>
        </div>
        <div class="d-flex flex-column min-w-0">
            <span class="fs-2hx fw-bold text-gray-900 lh-1" data-geni-stat>{{ number_format($value) }}</span>
            @if ($href)
                <a href="{{ $href }}" class="text-gray-500 text-hover-primary fw-semibold fs-6 mt-2">{{ $label }}</a>
            @else
                <span class="text-gray-500 fw-semibold fs-6 mt-2">{{ $label }}</span>
            @endif
        </div>
    </div>
</div>
