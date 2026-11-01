{{-- Translated badge for a labelled enum (status, reason, kind…). --}}
<span class="badge badge-light-{{ $status->badgeColor() }} fw-bold text-nowrap">{{ $status->label() }}</span>
