<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification telling a user that an administrator changed their account.
 *
 * The stored payload holds translation keys rather than rendered text, so the
 * notification is shown in the reader's current language.
 */
class AccountUpdated extends Notification
{
    use Queueable;

    public const TYPES = ['created', 'roles_changed', 'password_reset', 'activated', 'deactivated', 'updated'];

    public function __construct(public string $type, public ?User $actor = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title_key' => "admin.notifications.types.{$this->type}.title",
            'body_key' => "admin.notifications.types.{$this->type}.body",
            'actor' => $this->actor?->name,
            'url' => route('admin.profile.edit', absolute: false),
            'icon' => match ($this->type) {
                'password_reset' => 'ki-key',
                'roles_changed' => 'ki-shield-tick',
                'deactivated' => 'ki-lock',
                default => 'ki-user',
            },
        ];
    }
}
