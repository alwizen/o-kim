<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApplicationApprovedByDepartment extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $departmentLabel,
    ) {}

    protected function title(): string
    {
        return 'Approval '.$this->departmentLabel.' Diberikan';
    }

    protected function body(object $notifiable): string
    {
        return 'Pengajuan '.$this->applicationNumber.' telah disetujui oleh '.$this->departmentLabel.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-check-badge';
    }

    protected function iconColor(): string
    {
        return 'success';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval '.$this->departmentLabel.' — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pengajuan KIM Anda telah disetujui oleh '.$this->departmentLabel.'.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->action('Buka Pengajuan', url('/transportir/kim-applications'));
    }
}
