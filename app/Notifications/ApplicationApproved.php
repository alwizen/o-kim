<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApplicationApproved extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
    ) {}

    protected function title(): string
    {
        return 'Pengajuan KIM Disetujui';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — NOPOL '.$this->registrationNumber.' telah disetujui.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-check-circle';
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
            ->subject('Pengajuan KIM Disetujui — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pengajuan KIM Anda telah disetujui oleh Fuel Terminal Manager.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('KIM akan segera diterbitkan.')
            ->action('Buka Pengajuan', url('/transportir/kim-applications'));
    }
}
