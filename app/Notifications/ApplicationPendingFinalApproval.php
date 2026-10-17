<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApplicationPendingFinalApproval extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
    ) {}

    protected function title(): string
    {
        return 'Menunggu Approval Final';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — NOPOL '.$this->registrationNumber.' siap diputuskan.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-check-badge';
    }

    protected function iconColor(): string
    {
        return 'warning';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Menunggu Approval Final — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('HSSE, Distribusi, dan QQ telah menyetujui. Pengajuan menunggu approval final Anda.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->action('Buka Pengajuan', url('/admin/kim-applications'));
    }
}
