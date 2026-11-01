{{-- Search + filters for a server-side DataTable. Fields inside the slot are sent as filters[...]. --}}
@props(['id', 'searchId', 'placeholder' => null, 'organizations' => []])
<form id="{{ $id }}" class="card-title flex-wrap gap-3 w-100" role="search" onsubmit="return false">
    <div class="d-flex align-items-center position-relative my-1">
        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5"></i>
        <input type="search" id="{{ $searchId }}" class="form-control form-control-solid w-250px ps-13"
               placeholder="{{ $placeholder ?? __('admin.common.search') }}" aria-label="{{ $placeholder ?? __('admin.common.search') }}" />
    </div>
    {{ $slot }}
    @if (count($organizations) > 1)
        <select name="organization_id" class="form-select form-select-solid w-200px" aria-label="{{ __('admin.fields.organization_id') }}">
            <option value="">{{ __('admin.filters.all_organizations') }}</option>
            @foreach ($organizations as $orgId => $orgName)
                <option value="{{ $orgId }}">{{ $orgName }}</option>
            @endforeach
        </select>
    @endif
</form>
