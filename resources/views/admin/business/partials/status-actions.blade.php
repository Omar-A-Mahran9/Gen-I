{{--
    Status transition buttons for a show page.
    $url: PATCH endpoint; $current: enum; $targets: array of allowed target values; $enum: enum class.
--}}
@foreach ($targets as $target)
    @php($targetCase = $enum::from($target))
    <button type="button" class="btn btn-sm btn-light-{{ $targetCase->badgeColor() === 'secondary' ? 'primary' : $targetCase->badgeColor() }}"
            data-geni-action="{{ $url }}?status={{ $target }}" data-geni-method="PATCH" data-geni-reload="true"
            data-geni-confirm="{{ __('admin.messages.confirm_status', ['status' => $targetCase->label()]) }}">
        <i class="ki-outline ki-arrows-loop fs-5"></i>{{ __('admin.messages.set_status', ['status' => $targetCase->label()]) }}
    </button>
@endforeach
