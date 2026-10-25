<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class VehicleDocumentUploaded extends AppNotification
{
    public function __construct(
        public string $companyName,
        public string $registrationNumber,
        public string $documentTypeName,
    ) {}

    protected function title(): string
    {
        return 'Dokumen Kendaraan Diunggah';
    }

    protected function body(object $notifiable): string
    {
        return $this->documentTypeName.' untuk '.$this->registrationNumber.' dari '.$this->companyName.' menunggu verifikasi.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-document-arrow-up';
    }

    protected function iconColor(): string
    {
        return 'info';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/vehicles';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dokumen Kendaraan Baru — O-KIM')
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Dokumen kendaraan baru diunggah dan menunggu verifikasi.')
            ->line('Perusahaan: '.$this->companyName)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Jenis dokumen: '.$this->documentTypeName)
            ->action('Buka Data Kendaraan', url('/admin/vehicles'));
    }
}
