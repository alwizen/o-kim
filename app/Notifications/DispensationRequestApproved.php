<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Dikirim ke transportir ketika FTM menyetujui permohonan dispensasi.
 */
class DispensationRequestApproved extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $dueDate,
    ) {}

    protected function title(): string
    {
        return 'Permohonan KIM Sementara Disetujui';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — dispensasi disetujui hingga '.$this->dueDate.'.';
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
        return '/transportir/findings';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permohonan KIM Sementara Disetujui — '.$this->applicationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Fuel Terminal Manager menyetujui permohonan dispensasi atas temuan pemeriksaan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Batas perbaikan: '.$this->dueDate)
            ->line('Kendaraan dapat beroperasi dengan KIM sementara sampai batas tersebut. '
                .'Segera selesaikan perbaikan dan kirim tindak lanjut sebelum batas berakhir.')
            ->action('Buka Temuan', url('/transportir/findings'));
    }
}
