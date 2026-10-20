<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class InternalAccountRejected extends AppNotification
{
    public function __construct(
        public string $reason,
    ) {}

    protected function title(): string
    {
        return 'Pendaftaran Ditolak';
    }

    protected function body(object $notifiable): string
    {
        return 'Pendaftaran akun internal Anda ditolak. Alasan: '.$this->reason;
    }

    protected function icon(): string
    {
        return 'heroicon-o-x-circle';
    }

    protected function iconColor(): string
    {
        return 'danger';
    }

    protected function url(object $notifiable): ?string
    {
        return null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran O-KIM Ditolak')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Mohon maaf, pendaftaran akun internal Anda ditolak.')
            ->line('Alasan: '.$this->reason)
            ->line('Silakan hubungi super admin untuk informasi lebih lanjut.');
    }
}
