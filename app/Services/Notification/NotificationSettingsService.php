<?php

namespace App\Services\Notification;

use App\Enums\NotificationEvent;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Cache;

class NotificationSettingsService
{
    private const CACHE_KEY = 'okim.notification_settings';

    /**
     * Seluruh pengaturan, di-key berdasarkan event_key.
     *
     * @return array<string, array{mail_enabled: bool, roles: array<int, string>}>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return NotificationSetting::query()
                ->get()
                ->mapWithKeys(fn (NotificationSetting $setting) => [
                    $setting->event_key => [
                        'mail_enabled' => (bool) $setting->mail_enabled,
                        'roles' => $setting->roleNames(),
                    ],
                ])
                ->all();
        });
    }

    /**
     * Apakah email untuk event ini aktif. Event tanpa baris pengaturan
     * dianggap aktif (perilaku bawaan).
     */
    public function mailEnabled(NotificationEvent|string $event): bool
    {
        $key = $event instanceof NotificationEvent ? $event->value : $event;

        if ($event instanceof NotificationEvent && $event->locked()) {
            return true;
        }

        return (bool) ($this->all()[$key]['mail_enabled'] ?? true);
    }

    /**
     * Role penerima email untuk event ini.
     *
     * @return array<int, string>
     */
    public function rolesFor(NotificationEvent $event): array
    {
        return $this->all()[$event->value]['roles'] ?? $event->defaultRoles();
    }

    /**
     * Apakah notifiable termasuk role penerima email event ini.
     */
    public function notifiableHasRole(NotificationEvent $event, object $notifiable): bool
    {
        if (! method_exists($notifiable, 'hasAnyRole')) {
            return false;
        }

        $roles = $this->rolesFor($event);

        if ($roles === []) {
            return false;
        }

        return (bool) $notifiable->hasAnyRole($roles);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function save(NotificationEvent $event, bool $mailEnabled, array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles, fn ($role) => is_string($role) && $role !== '')));

        NotificationSetting::updateOrCreate(
            ['event_key' => $event->value],
            ['mail_enabled' => $mailEnabled, 'roles' => $roles],
        );

        $this->flush();
    }

    /**
     * Isi baris untuk seluruh event yang belum ada (idempotent).
     * Nilai yang sudah diubah super admin tidak ditimpa.
     */
    public function seedDefaults(): void
    {
        foreach (NotificationEvent::cases() as $event) {
            NotificationSetting::firstOrCreate(
                ['event_key' => $event->value],
                ['mail_enabled' => true, 'roles' => $event->defaultRoles()],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
