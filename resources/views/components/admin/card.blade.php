{{-- Metronic card. Slots: toolbar (header actions), footer. --}}
@props(['title' => null, 'subtitle' => null, 'icon' => null, 'flush' => false, 'bodyClass' => ''])
<div {{ $attributes->merge(['class' => 'card '.($flush ? 'card-flush' : '')]) }}>
    @if ($title || isset($toolbar))
        <div class="card-header {{ $flush ? 'pt-7' : '' }}">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-gray-900 d-flex align-items-center gap-2">
                    @if ($icon)<i class="ki-outline {{ $icon }} fs-2 text-primary"></i>@endif
                    {{ $title }}
                </span>
                @if ($subtitle)
                    <span class="text-gray-500 mt-1 fw-semibold fs-7">{{ $subtitle }}</span>
                @endif
            </h3>
            @isset($toolbar)
                <div class="card-toolbar">{{ $toolbar }}</div>
            @endisset
        </div>
    @endif
    <div class="card-body {{ $bodyClass }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card-footer d-flex justify-content-end gap-3 py-6">{{ $footer }}</div>
    @endisset
</div>
