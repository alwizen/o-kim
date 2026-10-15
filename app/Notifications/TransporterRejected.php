<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransporterRejected extends Notification
{
    use Queueable;

    public function __construct(public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran O-KIM Ditolak')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Mohon maaf, pendaftaran akun transportir Anda tidak dapat kami setujui.')
            ->line('Alasan: '.$this->reason)
            ->line('Jika Anda merasa ini adalah kesalahan, silakan hubungi admin Fuel Terminal Tegal.');
    }
}
