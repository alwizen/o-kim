<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class InternalRegistrationSubmitted extends AppNotification
{
    public function __construct(
        public string $name,
        public string $roleLabel,
    ) {}

    protected function title(): string
    {
        return 'Pendaftaran Pengguna Internal Baru';
    }

    protected function body(object $notifiable): string
    {
        return $this->name.' ('.$this->roleLabel.') menunggu verifikasi.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user-plus';
    }

    protected function iconColor(): string
    {
        return 'warning';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/users';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran Pengguna Internal Baru — O-KIM')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Ada pendaftaran pengguna internal baru yang menunggu verifikasi.')
            ->line('Nama: '.$this->name)
            ->line('Role: '.$this->roleLabel)
            ->action('Buka Menu Pengguna', url('/admin/users'));
    }
}
