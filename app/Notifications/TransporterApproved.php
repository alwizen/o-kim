<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TransporterApproved extends AppNotification
{
    protected function title(): string
    {
        return 'Pendaftaran Disetujui';
    }

    protected function body(object $notifiable): string
    {
        return 'Akun transportir Anda telah disetujui. Anda kini dapat mengakses panel transportir.';
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
        return '/transportir';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran O-KIM Disetujui')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Selamat! Pendaftaran akun transportir Anda telah disetujui.')
            ->line('Anda sekarang dapat mengakses panel transportir untuk mengelola kendaraan dan pengajuan KIM.')
            ->action('Masuk Panel Transportir', url('/transportir'));
    }
}
