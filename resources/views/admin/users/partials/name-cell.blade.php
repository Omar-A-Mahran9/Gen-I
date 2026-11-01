<div class="d-flex align-items-center">
    <div class="symbol symbol-circle symbol-40px overflow-hidden me-3">
        <span class="symbol-label fs-6 fw-bold bg-light-primary text-primary">{{ $user->initials() }}</span>
    </div>
    <div class="d-flex flex-column min-w-0">
        <a href="{{ route('admin.users.show', $user) }}" class="text-gray-800 text-hover-primary mb-1 fw-bold text-truncate">{{ $user->name }}</a>
        <span class="text-muted fs-7 text-truncate" dir="ltr">{{ $user->email }}</span>
    </div>
</div>
