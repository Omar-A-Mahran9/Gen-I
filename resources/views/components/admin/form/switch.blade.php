{{-- Boolean switch. A hidden 0 is sent when unchecked. --}}
@props(['name', 'label', 'checked' => false, 'hint' => null, 'id' => null, 'disabled' => false])
@php
    $id ??= str_replace(['[', ']', '.'], ['_', '', '_'], $name).'_input';
    $isChecked = (bool) old($name, $checked);
@endphp
<div class="fv-row mb-7" data-geni-field="{{ $name }}">
    <input type="hidden" name="{{ $name }}" value="0" @disabled($disabled) />
    <label class="form-check form-switch form-check-custom form-check-solid" for="{{ $id }}">
        <input class="form-check-input" type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" @checked($isChecked) @disabled($disabled) {{ $attributes }} />
        <span class="form-check-label fw-semibold text-gray-700">{{ $label }}</span>
    </label>
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback d-block" data-geni-error-for="{{ $name }}">@error($name){{ $message }}@enderror</div>
</div>
