<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes append-only audit entries for sensitive administrative events.
 *
 * Values under keys that look like secrets are always redacted.
 */
class AuditLogger
{
    protected const REDACTED = '[redacted]';

    protected const SENSITIVE_KEY_PATTERN = '/pass(word)?|token|secret|api[_-]?key|authorization|cookie/i';

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, ?Model $subject = null, array $properties = [], ?User $actor = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $actor ??= auth()->user();

        return AuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $this->sanitize($properties),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            'request_id' => $request?->attributes->get('request_id'),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    public function sanitize(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $properties[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $properties[$key] = $this->sanitize($value);
            }
        }

        return $properties;
    }
}
