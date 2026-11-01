{{-- Only future prices can be removed; effective prices are history and immutable. --}}
@if ($price->isFuture() && auth()->user()->can('update', $list))
    <button type="button" class="btn btn-icon btn-sm btn-light-danger" aria-label="{{ __('admin.common.delete') }}"
            data-geni-action="{{ route('admin.prices.destroy', $price) }}" data-geni-method="DELETE" data-geni-table="#geni_prices_table"
            data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i></button>
@else
    <i class="ki-outline ki-lock fs-5 text-muted" title="{{ __('admin.pricing.history_locked') }}"></i>
@endif
