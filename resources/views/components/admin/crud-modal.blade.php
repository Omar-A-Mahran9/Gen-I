{{--
    Add/edit modal for simple records (LMS crud_modal pattern), driven by resources/js/admin/crud-modal.js.

    Open it with:
      <button data-geni-crud-create="#id">                                   → empty form posting to $action
      <button data-geni-crud-edit="#id" data-url="…" data-record='{"…"}'>     → filled form, PUT to data-url

    Fields go in the slot. Wrap create-only fields in [data-geni-create-only]. Submission uses the
    standard AJAX form handling (inline errors, button loading state, toast); with $table set,
    that DataTable is reloaded after saving, otherwise the server's redirect is followed.
--}}
@props(['id', 'createTitle', 'editTitle', 'action', 'table' => null, 'size' => 'mw-650px'])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}_title" aria-hidden="true"
     data-geni-crud-modal data-store-url="{{ $action }}"
     data-create-title="{{ $createTitle }}" data-edit-title="{{ $editTitle }}"
     data-create-label="{{ __('admin.common.create') }}" data-edit-label="{{ __('admin.common.save') }}">
    <div class="modal-dialog modal-dialog-centered {{ $size }}">
        <form class="modal-content" method="POST" action="{{ $action }}" data-geni-ajax-form data-geni-close-modal="#{{ $id }}"
              @if ($table) data-geni-table="{{ $table }}" @endif novalidate>
            @csrf
            <input type="hidden" name="_method" value="POST" data-geni-method>
            <div class="modal-header">
                <h2 class="fw-bold fs-3 mb-0" id="{{ $id }}_title" data-geni-modal-title>{{ $createTitle }}</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="{{ __('admin.common.close') }}">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>
            <div {{ $attributes->merge(['class' => 'modal-body py-8 px-lg-10']) }}>
                {{ $slot }}
            </div>
            <div class="modal-footer flex-center gap-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                <x-admin.button type="submit" icon="ki-check"><span data-geni-submit-label>{{ __('admin.common.create') }}</span></x-admin.button>
            </div>
        </form>
    </div>
</div>
