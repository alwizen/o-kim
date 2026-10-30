<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class FindingDispensationExpired extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $kimNumber,
        public string $dueDate,
        public int $openItems,
    ) {}

    protected function title(): string
    {
        return 'KIM Sementara Kedaluwarsa';
    }

    protected function body(object $notifiable): string
    {
        return $this->kimNumber.' ('.$this->registrationNumber.') tidak berlaku lagi karena dispensasi '
            .'berakhir '.$this->dueDate.'. Ajukan pemeriksaan ulang untuk '.$this->openItems.' butir.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    protected function iconColor(): string
    {
        return 'danger';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KIM Sementara Kedaluwarsa — '.$this->kimNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('KIM sementara kendaraan Anda telah kedaluwarsa karena batas dispensasi terlampaui.')
            ->line('Nomor KIM: '.$this->kimNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Batas dispensasi: '.$this->dueDate)
            ->line('Jumlah butir yang perlu diperiksa ulang: '.$this->openItems)
            ->line('Silakan ajukan pemeriksaan ulang agar KIM dapat diterbitkan kembali.')
            ->action('Buka Pengajuan', url('/transportir/kim-applications'));
    }
}
