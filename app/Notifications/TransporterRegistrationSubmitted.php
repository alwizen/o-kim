<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TransporterRegistrationSubmitted extends AppNotification
{
    public function __construct(
        public string $companyName,
        public string $picName,
    ) {}

    protected function title(): string
    {
        return 'Pendaftaran Transportir Baru';
    }

    protected function body(object $notifiable): string
    {
        return $this->picName.' — '.$this->companyName.' menunggu verifikasi.';
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
        return '/admin/transporters';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran Transportir Baru — O-KIM')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Ada pendaftaran transportir baru yang menunggu verifikasi.')
            ->line('Perusahaan: '.$this->companyName)
            ->line('PIC: '.$this->picName)
            ->action('Buka Verifikasi Transportir', url('/admin/transporters'));
    }
}
