<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VehicleDocumentRejected extends Notification
{
    use Queueable;

    public function __construct(
        public string $documentTypeName,
        public string $registrationNumber,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Dokumen Kendaraan Ditolak',
            'body' => "Dokumen {$this->documentTypeName} ({$this->registrationNumber}) ditolak. Alasan: {$this->reason}",
            'icon' => 'heroicon-o-document-text',
            'color' => 'danger',
            'url' => '/transportir',
        ];
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
