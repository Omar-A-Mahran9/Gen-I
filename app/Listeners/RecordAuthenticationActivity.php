<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Audits web sign-in activity and records the last successful login.
 */
class RecordAuthenticationActivity
{
    public function __construct(protected AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->audit->log('auth.login', $event->user, ['guard' => $event->guard, 'remember' => $event->remember], $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.logout', $event->user, ['guard' => $event->guard], $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $this->audit->log(
            'auth.failed',
            $event->user instanceof User ? $event->user : null,
            ['guard' => $event->guard, 'email' => $event->credentials['email'] ?? null],
            null,
        );
    }
}
