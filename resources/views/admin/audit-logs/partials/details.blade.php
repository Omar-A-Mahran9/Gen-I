{{-- Compact, escaped rendering of sanitized audit properties. --}}
@php($properties = $log->properties ?? [])
@if ($properties === [])
    <span class="text-muted">—</span>
@else
    <div class="d-flex flex-column gap-1 fs-8">
        @foreach ($properties as $key => $value)
            <div class="text-truncate mw-350px">
                <span class="text-gray-500">{{ $key }}:</span>
                <span class="text-gray-800" dir="auto">
                    @if (is_array($value))
                        {{ \Illuminate\Support\Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 120) }}
                    @elseif (is_bool($value))
                        {{ $value ? 'true' : 'false' }}
                    @else
                        {{ \Illuminate\Support\Str::limit((string) $value, 120) }}
                    @endif
                </span>
            </div>
        @endforeach
    </div>
@endif
