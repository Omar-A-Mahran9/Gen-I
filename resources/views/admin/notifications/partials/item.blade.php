{{-- One database notification. Text is rendered from translation keys in the reader's language. --}}
@php
    $data = $notification->data;
    $unread = $notification->read_at === null;
@endphp
<div class="d-flex flex-stack py-4 {{ $unread ? '' : 'opacity-75' }}" data-geni-notification="{{ $notification->id }}">
    <div class="d-flex align-items-center min-w-0 me-2">
        <div class="symbol symbol-35px me-4 flex-shrink-0">
            <span class="symbol-label {{ $unread ? 'bg-light-primary' : 'bg-light' }}">
                <i class="ki-outline {{ $data['icon'] ?? 'ki-notification' }} fs-2 {{ $unread ? 'text-primary' : 'text-gray-500' }}"></i>
            </span>
        </div>
        <div class="mb-0 min-w-0">
            <div class="fs-6 text-gray-800 fw-bold text-truncate">
                {{ __($data['title_key'] ?? 'admin.notifications.title') }}
                @if ($unread)
                    <span class="bullet bullet-dot bg-danger h-6px w-6px ms-1 align-middle" data-geni-unread-dot></span>
                @endif
            </div>
            <div class="text-gray-600 fs-7">{{ __($data['body_key'] ?? '') }}</div>
            @if (! empty($data['actor']))
                <div class="text-gray-500 fs-8">{{ __('admin.notifications.by', ['name' => $data['actor']]) }}</div>
            @endif
        </div>
    </div>
    <div class="d-flex flex-column align-items-end flex-shrink-0 gap-1">
        <span class="badge badge-light fs-8" title="{{ format_datetime($notification->created_at) }}">{{ $notification->created_at->diffForHumans() }}</span>
        @if ($unread && ($showActions ?? false))
            <button type="button" class="btn btn-sm btn-link btn-color-primary p-0 fs-8"
                    data-geni-action="{{ route('admin.notifications.read', $notification->id) }}" data-geni-method="POST"
                    data-geni-reload="true">{{ __('admin.notifications.mark_read') }}</button>
        @endif
    </div>
</div>
