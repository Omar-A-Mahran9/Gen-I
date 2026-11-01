{{-- Language switcher. $compact = true renders a labelled button (auth pages). --}}
@php($compact = $compact ?? false)
<div class="d-flex align-items-center">
    @if ($compact)
        <button type="button" class="btn btn-sm btn-light btn-active-light-primary fw-bold d-flex align-items-center gap-2"
                data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end" aria-label="{{ __('admin.common.language') }}">
            <i class="ki-outline ki-global fs-4"></i>
            <span>{{ $localeMeta['name'] }}</span>
        </button>
    @else
        <button type="button" class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px"
                data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end"
                title="{{ __('admin.common.language') }}" aria-label="{{ __('admin.common.language') }}">
            <span class="fw-bolder fs-7">{{ $localeMeta['short'] }}</span>
        </button>
    @endif
    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold w-175px py-4" data-kt-menu="true">
        @foreach ($availableLocales as $code => $meta)
            <div class="menu-item px-3">
                <a href="{{ route('admin.locale.switch', $code) }}" lang="{{ $code }}" dir="{{ $meta['dir'] }}"
                   class="menu-link d-flex px-5 {{ $code === $currentLocale ? 'active' : '' }}" data-geni-locale="{{ $code }}">
                    <span class="badge badge-light-primary fw-bold me-3">{{ $meta['short'] }}</span>
                    {{ $meta['name'] }}
                </a>
            </div>
        @endforeach
    </div>
</div>
