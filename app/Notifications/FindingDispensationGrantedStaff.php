<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class FindingDispensationGrantedStaff extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $departmentLabel,
        public string $dueDate,
        public string $reason,
        public string $grantedBy,
    ) {}

    protected function title(): string
    {
        return 'Dispensasi Temuan Diberikan';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — '.$this->departmentLabel.' didispensasi oleh '
            .$this->grantedBy.' hingga '.$this->dueDate.'.';
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
        return '/admin/findings';
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
            ->line('Diberikan oleh: '.$this->grantedBy)
            ->line('Batas perbaikan: '.$this->dueDate)
            ->line('Alasan: '.$this->reason)
            ->action('Buka Temuan', url('/admin/findings'));
    }
}
