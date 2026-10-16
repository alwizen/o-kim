<?php

namespace App\Support;

use App\Enums\AccountStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class AppNotifier
{
    /**
     * Kirim notifikasi database (lonceng Filament) ke satu pengguna.
     */
    public static function toUser(
        User $user,
        string $title,
        string $body = '',
        string $icon = 'heroicon-o-bell',
        string $color = 'primary',
        ?string $url = null,
    ): void {
        $notification = FilamentNotification::make()
            ->title($title)
            ->icon($icon)
            ->iconColor($color);

        if ($body !== '') {
            $notification->body($body);
        }

        if ($url !== null) {
            $notification->actions([
                Action::make('lihat')->label('Lihat')->url($url)->markAsRead(),
            ]);
        }

        // notifyNow agar notifikasi langsung tersimpan tanpa menunggu queue worker.
        $user->notifyNow($notification->toDatabase());
    }

    /**
     * Kirim notifikasi ke seluruh pengguna internal yang memiliki permission tertentu.
     *
     * @param  string|array<int, string>  $permissions
     */
    public static function toStaffWithPermission(
        string|array $permissions,
        string $title,
        string $body = '',
        string $icon = 'heroicon-o-bell',
        string $color = 'primary',
        ?string $url = null,
    ): void {
        $permissions = (array) $permissions;

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where('account_status', AccountStatus::Approved)
            ->permission($permissions)
            ->get();

        foreach ($users as $user) {
            self::toUser($user, $title, $body, $icon, $color, $url);
        }
    }

    /**
     * Kirim notifikasi ke seluruh pemilik (PIC) sebuah perusahaan transportir.
     */
    public static function toTransporter(
        int $transporterId,
        string $title,
        string $body = '',
        string $icon = 'heroicon-o-bell',
        string $color = 'primary',
        ?string $url = null,
    ): void {
        $users = User::query()
            ->whereHas('transporters', fn ($query) => $query->where('transporters.id', $transporterId))
            ->get();

        foreach ($users as $user) {
            self::toUser($user, $title, $body, $icon, $color, $url);
        }
    }

    /**
     * Tandai notifikasi tertentu sebagai sudah dibaca (untuk diarahkan dari email/WA dsb).
     */
    public static function markRead(User $user, DatabaseNotification $notification): void
    {
        if ($notification->notifiable_id === $user->getKey() && $notification->read_at === null) {
            $notification->markAsRead();
        }
    }
}
