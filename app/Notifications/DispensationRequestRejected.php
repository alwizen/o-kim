<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Dikirim ke transportir ketika FTM menolak permohonan dispensasi.
 */
class DispensationRequestRejected extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $reason,
    ) {}

    protected function title(): string
    {
        return 'Permohonan KIM Sementara Ditolak';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — permohonan dispensasi ditolak FTM.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-x-circle';
    }

    protected function iconColor(): string
    {
        return 'danger';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/findings';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permohonan KIM Sementara Ditolak — '.$this->applicationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Fuel Terminal Manager menolak permohonan dispensasi atas temuan pemeriksaan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Alasan penolakan: '.$this->reason)
            ->line('Silakan selesaikan perbaikan dan kirim tindak lanjut, atau ajukan permohonan ulang bila diperlukan.')
            ->action('Buka Temuan', url('/transportir/findings'));
    }
}
