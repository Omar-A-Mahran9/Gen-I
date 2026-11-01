{{-- Text-like input with label, hint and server-side/AJAX validation feedback. --}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'id' => null, 'solid' => true])
@php
    $id ??= str_replace(['[', ']', '.'], ['_', '', '_'], $name).'_input';
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' ? null : old($errorKey, $value);
@endphp
<div class="fv-row mb-7" data-geni-field="{{ $errorKey }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label fw-semibold fs-6 {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
           @if ($required) required @endif
           @error($errorKey) aria-invalid="true" @enderror
           aria-describedby="{{ $id }}_feedback{{ $hint ? ' '.$id.'_hint' : '' }}"
           {{ $attributes->merge(['class' => 'form-control '.($solid ? 'form-control-solid ' : '').($errors->has($errorKey) ? 'is-invalid' : '')]) }} />
    @if ($hint)
        <div class="form-text" id="{{ $id }}_hint">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback" id="{{ $id }}_feedback" data-geni-error-for="{{ $errorKey }}">@error($errorKey){{ $message }}@enderror</div>
</div>
