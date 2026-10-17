<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class VehicleDocumentRejected extends AppNotification
{
    public function __construct(
        public string $documentTypeName,
        public string $registrationNumber,
        public string $reason,
    ) {}

    protected function title(): string
    {
        return 'Dokumen Kendaraan Ditolak';
    }

    protected function body(object $notifiable): string
    {
        return "Dokumen {$this->documentTypeName} ({$this->registrationNumber}) ditolak. Alasan: {$this->reason}";
    }

    protected function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    protected function iconColor(): string
    {
        return 'danger';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dokumen Kendaraan Ditolak — O-KIM')
            ->greeting('Halo '.$notifiable->name.',')
            ->line("Dokumen {$this->documentTypeName} untuk kendaraan {$this->registrationNumber} ditolak.")
            ->line('Alasan: '.$this->reason)
            ->line('Silakan unggah ulang dokumen yang sudah diperbaiki melalui panel transportir.')
            ->action('Buka Panel Transportir', url('/transportir'));
    }
}
