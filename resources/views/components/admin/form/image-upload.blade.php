{{--
    Metronic image-input with preview, change and remove.
    Sends the file as $name and "<name>_remove=1" when the user removes a custom image.
--}}
@props(['name', 'label', 'url', 'custom' => false, 'accept' => 'image/png,image/jpeg,image/webp', 'hint' => null, 'size' => 'w-150px h-100px', 'contain' => true, 'dark' => false])
<div class="fv-row mb-7" data-geni-field="{{ $name }}">
    <div class="form-label fw-semibold fs-6">{{ $label }}</div>
    <div class="image-input image-input-outline" data-kt-image-input="true">
        <div class="image-input-wrapper {{ $size }} {{ $contain ? 'bgi-size-contain bgi-position-center' : '' }}"
             style="background-image: url('{{ $url }}'); background-repeat: no-repeat;{{ $dark ? ' background-color: #1e1e2d;' : '' }}"></div>

        <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="change"
               title="{{ __('admin.settings.change') }}">
            <i class="ki-outline ki-pencil fs-7"></i>
            <input type="file" name="{{ $name }}" accept="{{ $accept }}" />
            <input type="hidden" name="{{ $name }}_remove" value="0" />
        </label>
        <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="cancel"
              title="{{ __('admin.common.cancel') }}">
            <i class="ki-outline ki-cross fs-2"></i>
        </span>
        @if ($custom)
            <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="remove"
                  title="{{ __('admin.settings.remove') }}">
                <i class="ki-outline ki-cross fs-2"></i>
            </span>
        @endif
    </div>
    <div class="mt-2">
        <span class="badge {{ $custom ? 'badge-light-success' : 'badge-light' }}">{{ $custom ? __('admin.settings.custom') : __('admin.settings.placeholder') }}</span>
    </div>
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback d-block" data-geni-error-for="{{ $name }}">@error($name){{ $message }}@enderror</div>
</div>
