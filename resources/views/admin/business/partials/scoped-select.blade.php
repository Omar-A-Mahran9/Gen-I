{{--
    Select whose options belong to an organization (data-organization), filtered in add/edit modals.
    $name, $label, $options (iterable of models), $text (callable model → label), $placeholder, $hint, $excludeSelf, $select2, $modal
--}}
<div class="fv-row mb-7" data-geni-field="{{ $name }}">
    <label for="{{ $name }}_modal_input" class="form-label fw-semibold fs-6">{{ $label }}</label>
    <select name="{{ $name }}" id="{{ $name }}_modal_input" class="form-select form-select-solid" data-geni-filter-organization
            @if ($excludeSelf ?? false) data-geni-exclude-self @endif
            @if ($select2 ?? false) data-control="select2" data-placeholder="{{ $placeholder }}" data-allow-clear="true" @endif>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $option)
            <option value="{{ $option->id }}" data-organization="{{ $option->organization_id }}">{{ $text($option) }}</option>
        @endforeach
    </select>
    @isset($hint)<div class="form-text">{{ $hint }}</div>@endisset
    <div class="invalid-feedback" data-geni-error-for="{{ $name }}"></div>
</div>
