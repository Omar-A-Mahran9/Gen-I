{{--
    Select input. options: [value => label]. Set select2 for searchable selects,
    or ajaxUrl for Select2 AJAX (endpoint returns {results: [{id, text}]}).
--}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'hint' => null, 'id' => null,
        'placeholder' => null, 'multiple' => false, 'select2' => false, 'ajaxUrl' => null, 'solid' => true])
@php
    $id ??= str_replace(['[', ']', '.'], ['_', '', '_'], $name).'_input';
    $errorKey = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $selected = collect((array) old($errorKey, $value))->map(fn ($v) => (string) $v)->all();
@endphp
<div class="fv-row mb-7" data-geni-field="{{ $errorKey }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label fw-semibold fs-6 {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" @if ($multiple) multiple @endif @if ($required) required @endif
            @if ($select2 || $ajaxUrl) data-control="select2" data-placeholder="{{ $placeholder ?? __('admin.common.select_option') }}" data-allow-clear="{{ $required ? 'false' : 'true' }}" @endif
            @if ($ajaxUrl) data-geni-select2-ajax="{{ $ajaxUrl }}" @endif
            {{ $attributes->merge(['class' => 'form-select '.($solid ? 'form-select-solid ' : '').($errors->has($errorKey) ? 'is-invalid' : '')]) }}>
        @if ($placeholder !== null && ! $multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $selected, true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback" data-geni-error-for="{{ $errorKey }}">@error($errorKey){{ $message }}@enderror</div>
</div>
