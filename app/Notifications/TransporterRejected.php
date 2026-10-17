<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TransporterRejected extends AppNotification
{
    public function __construct(public string $reason) {}

    protected function title(): string
    {
        return 'Pendaftaran Ditolak';
    }

    protected function body(object $notifiable): string
    {
        return 'Pendaftaran Anda ditolak. Alasan: '.$this->reason;
    }

    protected function icon(): string
    {
        return 'heroicon-o-x-circle';
    }

    protected function iconColor(): string
    {
        return 'danger';
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
