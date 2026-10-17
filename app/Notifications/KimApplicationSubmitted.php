<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class KimApplicationSubmitted extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $companyName,
    ) {}

    protected function title(): string
    {
        return 'Pengajuan KIM Baru';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — NOPOL '.$this->registrationNumber.' dari '.$this->companyName.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-document-plus';
    }

    protected function iconColor(): string
    {
        return 'info';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan KIM Baru — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pengajuan KIM baru telah dikirim dan menunggu pemeriksaan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Perusahaan: '.$this->companyName)
            ->action('Buka Pengajuan', url('/admin/kim-applications'));
    }
}
