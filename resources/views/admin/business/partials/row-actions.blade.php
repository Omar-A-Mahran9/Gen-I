{{-- Row actions for DataTables: view / edit / delete, each shown only when the policy allows it. --}}
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
    @if ($canEdit)
        <a href="{{ route("admin.{$route}.edit", $param) }}" class="btn btn-icon btn-sm btn-light btn-active-light-primary" title="{{ __('admin.common.edit') }}" aria-label="{{ __('admin.common.edit') }}">
            <i class="ki-outline ki-pencil fs-4"></i>
        </a>
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
