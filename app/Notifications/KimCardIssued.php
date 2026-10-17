<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class KimCardIssued extends AppNotification
{
    public function __construct(
        public string $kimNumber,
        public string $registrationNumber,
        public string $expiresAt,
    ) {}

    protected function title(): string
    {
        return 'KIM Telah Diterbitkan';
    }

    protected function body(object $notifiable): string
    {
        return $this->kimNumber.' untuk NOPOL '.$this->registrationNumber.' berlaku hingga '.$this->expiresAt.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-identification';
    }

    protected function iconColor(): string
    {
        return 'success';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KIM Telah Diterbitkan — '.$this->kimNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Kartu Izin Masuk kendaraan Anda telah diterbitkan.')
            ->line('Nomor KIM: '.$this->kimNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Berlaku hingga: '.$this->expiresAt)
            ->action('Unduh KIM', url('/transportir/kim-applications'));
    }
}
