{{--
    Row actions for DataTables: view / edit / delete, each shown only when the policy allows it.
    Simple records edit in a modal: pass editModal (selector) and record (array of form values);
    otherwise the edit button links to the dedicated edit page.
    Optional quick status toggle: statusUrl + statusTarget (enum case).
--}}
@php
    $user = auth()->user();
    $canEdit = $user->can('update', $model);
    $canDelete = $user->can('delete', $model);
@endphp
<div class="d-flex justify-content-end gap-1">
    @unless ($noShow ?? false)
        <a href="{{ route("admin.{$route}.show", $param) }}" class="btn btn-icon btn-sm btn-light btn-active-light-primary" title="{{ __('admin.common.view') }}" aria-label="{{ __('admin.common.view') }}">
            <i class="ki-outline ki-eye fs-4"></i>
        </a>
    @endunless
    @if ($canEdit && isset($statusUrl, $statusTarget))
        <button type="button" class="btn btn-icon btn-sm btn-light btn-active-light-primary" title="{{ __('admin.messages.set_status', ['status' => $statusTarget->label()]) }}"
                aria-label="{{ __('admin.messages.set_status', ['status' => $statusTarget->label()]) }}"
                data-geni-action="{{ $statusUrl }}?status={{ $statusTarget->value }}" data-geni-method="PATCH"
                data-geni-confirm="{{ __('admin.messages.confirm_status', ['status' => $statusTarget->label()]) }}"
                data-geni-table="{{ $table ?? '[data-geni-datatable]' }}">
            <i class="ki-outline ki-arrows-loop fs-4"></i>
        </button>
    @endif
    @if ($canEdit)
        @isset($editModal)
            <button type="button" class="btn btn-icon btn-sm btn-light btn-active-light-primary" title="{{ __('admin.common.edit') }}" aria-label="{{ __('admin.common.edit') }}"
                    data-geni-crud-edit="{{ $editModal }}" data-url="{{ route("admin.{$route}.update", $param) }}" data-record="{{ json_encode($record) }}">
                <i class="ki-outline ki-pencil fs-4"></i>
            </button>
        @else
            <a href="{{ route("admin.{$route}.edit", $param) }}" class="btn btn-icon btn-sm btn-light btn-active-light-primary" title="{{ __('admin.common.edit') }}" aria-label="{{ __('admin.common.edit') }}">
                <i class="ki-outline ki-pencil fs-4"></i>
            </a>
        @endisset
    @endif
    @if ($canDelete)
        <button type="button" class="btn btn-icon btn-sm btn-light btn-active-light-danger" title="{{ __('admin.common.delete') }}" aria-label="{{ __('admin.common.delete') }}"
                data-geni-action="{{ route("admin.{$route}.destroy", $param) }}" data-geni-method="DELETE"
                data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"
                data-geni-table="{{ $table ?? '[data-geni-datatable]' }}">
            <i class="ki-outline ki-trash fs-4"></i>
        </button>
    @endif
</div>
