@php
    $key = 'admin.audit.events.'.str_replace('.', '_', $event);
    $label = \Illuminate\Support\Facades\Lang::has($key) ? __($key) : $event;
    $color = match (true) {
        str_ends_with($event, 'failed'), str_ends_with($event, 'deleted'), str_ends_with($event, 'deactivated') => 'danger',
        str_starts_with($event, 'auth.') => 'info',
        str_starts_with($event, 'role.'), str_ends_with($event, 'roles_changed') => 'warning',
        str_starts_with($event, 'settings.') => 'primary',
        default => 'success',
    };
@endphp
<span class="badge badge-light-{{ $color }} fw-bold text-nowrap" title="{{ $event }}">{{ $label }}</span>
