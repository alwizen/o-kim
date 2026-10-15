<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransporterRegistered extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
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
