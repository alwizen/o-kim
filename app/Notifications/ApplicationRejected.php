<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApplicationRejected extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $departmentLabel,
        public string $reason,
    ) {}

    protected function title(): string
    {
        return 'Pengajuan KIM Ditolak';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' ditolak oleh '.$this->departmentLabel.'. Alasan: '.$this->reason;
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
        return '/transportir/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan KIM Ditolak — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pengajuan KIM Anda ditolak.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Ditolek oleh: '.$this->departmentLabel)
            ->line('Alasan: '.$this->reason)
            ->action('Buka Pengajuan', url('/transportir/kim-applications'));
    }
}
