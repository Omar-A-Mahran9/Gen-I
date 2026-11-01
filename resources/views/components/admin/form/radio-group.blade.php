{{-- Radio group rendered as Metronic option cards. options: [value => label] or [value => ['label' => …, 'icon' => …]]. --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'hint' => null])
@php($current = (string) old($name, $value))
<div class="fv-row mb-7" data-geni-field="{{ $name }}" role="radiogroup" @if ($label) aria-label="{{ $label }}" @endif>
    @if ($label)
        <div class="form-label fw-semibold fs-6 {{ $required ? 'required' : '' }}">{{ $label }}</div>
    @endif
    <div class="d-flex flex-wrap gap-3">
        @foreach ($options as $optionValue => $option)
            @php($optionLabel = is_array($option) ? $option['label'] : $option)
            @php($optionIcon = is_array($option) ? ($option['icon'] ?? null) : null)
            <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center gap-2 px-5 py-3 {{ $current === (string) $optionValue ? 'active' : '' }}" data-kt-button="true">
                <input class="btn-check" type="radio" name="{{ $name }}" value="{{ $optionValue }}" @checked($current === (string) $optionValue) />
                @if ($optionIcon)<i class="ki-outline {{ $optionIcon }} fs-3"></i>@endif
                <span class="fw-semibold">{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback d-block" data-geni-error-for="{{ $name }}">@error($name){{ $message }}@enderror</div>
</div>
