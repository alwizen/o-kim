<?php

namespace App\Notifications;

use App\Notifications\Channels\QueuedMailChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi O-KIM.
 *
 * - Email dikirim melalui queue (channel QueuedMailChannel) agar tidak memblokir request.
 * - Notifikasi database memakai payload Filament sehingga muncul pada lonceng panel.
 */
abstract class AppNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [QueuedMailChannel::class, 'database'];
    }

    abstract protected function title(): string;

    abstract protected function body(object $notifiable): string;

    abstract public function toMail(object $notifiable): MailMessage;

    protected function icon(): string
    {
        return 'heroicon-o-bell';
    }

    protected function iconColor(): string
    {
        return 'primary';
    }

    protected function url(object $notifiable): ?string
    {
        return null;
    }

    protected function actionLabel(): string
    {
        return 'Lihat';
    }

    /**
     * Payload notifikasi database (dibaca oleh lonceng Filament).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->filamentPayload($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->filamentPayload($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filamentPayload(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body($notifiable))
            ->icon($this->icon())
            ->iconColor($this->iconColor());

        if ($url = $this->url($notifiable)) {
            $notification->actions([
                Action::make('lihat')
                    ->label($this->actionLabel())
                    ->url($url)
                    ->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
