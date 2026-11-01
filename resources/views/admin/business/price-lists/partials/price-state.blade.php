@if ($price->isFuture())
    <span class="badge badge-light-info">{{ __('admin.pricing.state.future') }}</span>
@elseif ($price->isCurrent())
    <span class="badge badge-light-success">{{ __('admin.pricing.state.current') }}</span>
@else
    <span class="badge badge-light">{{ __('admin.pricing.state.past') }}</span>
@endif
