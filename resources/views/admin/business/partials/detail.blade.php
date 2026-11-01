{{-- Label/value row for details cards. --}}
<div class="d-flex flex-stack py-3 border-bottom border-gray-200 border-bottom-dashed">
    <span class="text-gray-500 fw-semibold fs-7 me-4">{{ $label }}</span>
    <span class="fw-bold text-gray-800 fs-7 text-end" @isset($ltr) dir="ltr" @endisset>{!! $value ?? '<span class="text-muted">—</span>' !!}</span>
</div>
