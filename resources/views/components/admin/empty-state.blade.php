{{-- Empty state with light/dark illustration. --}}
@props(['title', 'body' => null, 'compact' => false])
<div {{ $attributes->merge(['class' => 'd-flex flex-column flex-center text-center '.($compact ? 'py-6' : 'py-12')]) }}>
    @unless ($compact)
        <img src="{{ asset('assets/admin/media/illustrations/empty.png') }}" class="mw-150px mb-6 theme-light-show" alt="" />
        <img src="{{ asset('assets/admin/media/illustrations/empty-dark.png') }}" class="mw-150px mb-6 theme-dark-show" alt="" />
    @endunless
    <div class="fs-5 fw-bold text-gray-800 mb-2">{{ $title }}</div>
    @if ($body)
        <div class="fs-6 text-gray-500 mw-500px">{{ $body }}</div>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
