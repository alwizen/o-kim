<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApplicationPendingDepartmentApproval extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $departmentLabel,
        public ?string $applicationId = null,
    ) {}

    protected function title(): string
    {
        return 'Menunggu Approval '.$this->departmentLabel;
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — NOPOL '.$this->registrationNumber.' siap di-approval '.$this->departmentLabel.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    protected function iconColor(): string
    {
        return 'warning';
    }

    protected function url(object $notifiable): ?string
    {
        return $this->applicationId !== null
            ? '/admin/kim-applications/'.$this->applicationId
            : '/admin/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Menunggu Approval '.$this->departmentLabel.' — '.$this->applicationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Checklist pemeriksaan fungsi '.$this->departmentLabel.' telah selesai.')
            ->line('Pengajuan menunggu approval Anda.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->action('Buka Pengajuan', url($this->url($notifiable)));
    }
}
