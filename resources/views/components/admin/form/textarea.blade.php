@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'id' => null, 'rows' => 4])
@php
    $id ??= str_replace(['[', ']', '.'], ['_', '', '_'], $name).'_input';
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div class="fv-row mb-7" data-geni-field="{{ $errorKey }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label fw-semibold fs-6 {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @if ($required) required @endif
        {{ $attributes->merge(['class' => 'form-control form-control-solid '.($errors->has($errorKey) ? 'is-invalid' : '')]) }}>{{ old($errorKey, $value) }}</textarea>
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback" data-geni-error-for="{{ $errorKey }}">@error($errorKey){{ $message }}@enderror</div>
</div>
