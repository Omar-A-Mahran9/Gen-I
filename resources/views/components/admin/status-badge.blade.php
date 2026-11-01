@props(['active'])
<span {{ $attributes->merge(['class' => 'badge fw-bold '.($active ? 'badge-light-success' : 'badge-light-danger')]) }}>
    {{ $active ? __('admin.common.active') : __('admin.common.inactive') }}
</span>
