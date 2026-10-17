<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class VehicleDocumentVerified extends AppNotification
{
    public function __construct(
        public string $documentTypeName,
        public string $registrationNumber,
    ) {}

    protected function title(): string
    {
        return 'Dokumen Kendaraan Diverifikasi';
    }

    protected function body(object $notifiable): string
    {
        return $this->documentTypeName.' untuk kendaraan '.$this->registrationNumber.' telah diverifikasi.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-check-badge';
    }

    protected function iconColor(): string
    {
        return 'success';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/vehicles';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dokumen Kendaraan Diverifikasi — O-KIM')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Dokumen kendaraan Anda telah diverifikasi oleh petugas.')
            ->line('Jenis dokumen: '.$this->documentTypeName)
            ->line('NOPOL: '.$this->registrationNumber)
            ->action('Buka Data Kendaraan', url('/transportir/vehicles'));
    }
}
