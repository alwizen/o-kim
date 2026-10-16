<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransporterApproved extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pendaftaran Disetujui',
            'body' => 'Akun transportir Anda telah disetujui. Anda kini dapat mengakses panel transportir.',
            'icon' => 'heroicon-o-check-badge',
            'color' => 'success',
            'url' => '/transportir',
        ];
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
