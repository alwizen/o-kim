<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class FindingActionSubmitted extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $departmentLabel,
    ) {}

    protected function title(): string
    {
        return 'Tindak Lanjut Temuan Dikirim';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — '.$this->departmentLabel.' menunggu verifikasi tindak lanjut.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-wrench-screwdriver';
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
            ->subject('Tindak Lanjut Temuan — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Transportir telah mengirim tindak lanjut atas temuan pemeriksaan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Fungsi: '.$this->departmentLabel)
            ->action('Buka Temuan', url('/admin/findings'));
    }
}
