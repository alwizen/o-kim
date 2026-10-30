<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Dikirim ke FTM ketika transportir mengajukan permohonan dispensasi temuan.
 */
class DispensationRequestSubmitted extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $departmentLabel,
        public string $findingDescription,
        public string $requesterName,
        public string $reason,
    ) {}

    protected function title(): string
    {
        return 'Permohonan KIM Sementara Diajukan';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — '.$this->departmentLabel.' menunggu keputusan dispensasi.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-inbox-arrow-down';
    }

    protected function iconColor(): string
    {
        return 'warning';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/dispensation-requests';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permohonan KIM Sementara — '.$this->applicationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Transportir mengajukan permohonan dispensasi (KIM Sementara) atas temuan pemeriksaan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Fungsi: '.$this->departmentLabel)
            ->line('Temuan: '.$this->findingDescription)
            ->line('Diajukan oleh: '.$this->requesterName)
            ->line('Alasan permohonan: '.$this->reason)
            ->line('Silakan tinjau permohonan dan tentukan batas waktu perbaikan bila disetujui.')
            ->action('Buka Pengajuan KIM Sementara', url('/admin/dispensation-requests'));
    }
}
