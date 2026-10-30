<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class FindingDispensationGranted extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $departmentLabel,
        public string $dueDate,
        public string $reason,
    ) {}

    protected function title(): string
    {
        return 'Temuan Diberi Dispensasi FTM';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — '.$this->departmentLabel.' didispensasi hingga '.$this->dueDate.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-clock';
    }

    protected function iconColor(): string
    {
        return 'warning';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/findings';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dispensasi Temuan — '.$this->applicationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Fuel Terminal Manager memberikan dispensasi atas temuan pemeriksaan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Fungsi: '.$this->departmentLabel)
            ->line('Batas perbaikan: '.$this->dueDate)
            ->line('Alasan: '.$this->reason)
            ->line('Kendaraan dapat beroperasi dengan KIM sementara sampai batas tersebut. '
                .'Segera selesaikan perbaikan dan kirim tindak lanjut sebelum batas berakhir.')
            ->action('Buka Temuan', url('/transportir/findings'));
    }
}
