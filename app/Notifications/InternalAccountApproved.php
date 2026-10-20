<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class InternalAccountApproved extends AppNotification
{
    protected function title(): string
    {
        return 'Akun Disetujui';
    }

    protected function body(object $notifiable): string
    {
        return 'Akun internal Anda telah disetujui. Anda kini dapat mengakses panel admin.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-check-badge';
    }

    protected function iconColor(): string
    {
        return 'success';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun O-KIM Disetujui')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Selamat! Akun internal Anda telah disetujui oleh super admin.')
            ->line('Anda sekarang dapat mengakses panel admin sesuai kewenangan Anda.')
            ->action('Masuk Panel Admin', url('/admin/login'));
    }
}
