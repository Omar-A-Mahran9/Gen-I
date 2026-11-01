{{-- Bootstrap modal. Slots: default (body), footer. --}}
@props(['id', 'title', 'size' => 'mw-650px'])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $size }}">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold fs-3 mb-0" id="{{ $id }}_title">{{ $title }}</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="{{ __('admin.common.close') }}">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>
            <div {{ $attributes->merge(['class' => 'modal-body py-8 px-lg-10']) }}>
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="modal-footer flex-center gap-3">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
