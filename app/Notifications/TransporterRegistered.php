<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TransporterRegistered extends AppNotification
{
    protected function title(): string
    {
        return 'Pendaftaran Diterima';
    }

    protected function body(object $notifiable): string
    {
        return 'Pendaftaran akun transportir Anda telah kami terima dan akan diverifikasi admin.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user-plus';
    }

    protected function iconColor(): string
    {
        return 'info';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran O-KIM Diterima')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pendaftaran akun transportir Anda di O-KIM Fuel Terminal Tegal telah kami terima.')
            ->line('Tim kami akan memeriksa data perusahaan Anda sebelum akun diaktifkan.')
            ->line('Anda akan menerima pemberitahuan setelah proses verifikasi selesai.');
    }
}
