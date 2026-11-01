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
            .'berakhir '.$this->dueDate.'. Buat pengajuan Perpanjangan untuk '.$this->openItems
            .' butir yang belum tuntas.';
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
            ->line('Jumlah butir yang belum tuntas: '.$this->openItems)
            ->line('KIM sementara tidak berlaku lagi. Silakan buat pengajuan Perpanjangan untuk '
                .'kendaraan ini agar dapat diperiksa ulang dan KIM diterbitkan kembali.')
            ->action('Buat Pengajuan Perpanjangan', url('/transportir/kim-applications/create'));
    }
}
