{{-- Single checkbox (use name="items[]" for checkbox groups). --}}
@props(['name', 'label', 'value' => '1', 'checked' => false, 'disabled' => false, 'id' => null, 'hint' => null])
@php($id ??= str_replace(['[', ']', '.'], ['_', '', '_'], $name).'_'.\Illuminate\Support\Str::slug((string) $value, '_'))
<label class="form-check form-check-custom form-check-solid align-items-start {{ $disabled ? 'opacity-50' : '' }}" for="{{ $id }}">
    <input class="form-check-input" type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked($checked) @disabled($disabled) {{ $attributes }} />
    <span class="form-check-label d-flex flex-column">
        <span class="fw-semibold text-gray-800">{{ $label }}</span>
        @if ($hint)<span class="text-muted fs-8">{{ $hint }}</span>@endif
    </span>
</label>
