@props(['enum', 'name' => 'status', 'label' => null])
<select name="{{ $name }}" class="form-select form-select-solid w-175px" aria-label="{{ $label ?? __('admin.fields.status') }}">
    <option value="">{{ __('admin.filters.all') }} · {{ $label ?? __('admin.fields.status') }}</option>
    @foreach ($enum::options() as $value => $text)
        <option value="{{ $value }}">{{ $text }}</option>
    @endforeach
</select>
