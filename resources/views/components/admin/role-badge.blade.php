@props(['name'])
@php
    $label = \Illuminate\Support\Facades\Lang::has("admin.roles.names.{$name}") ? __("admin.roles.names.{$name}") : $name;
    $color = match ($name) { config('admin.super_admin_role') => 'danger', 'admin' => 'primary', 'manager' => 'info', default => 'secondary' };
@endphp
<span {{ $attributes->merge(['class' => "badge badge-light-{$color} fw-bold me-1 mb-1"]) }}>{{ $label }}</span>
