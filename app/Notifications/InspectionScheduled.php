<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class InspectionScheduled extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $scheduledAt,
    ) {}

    protected function title(): string
    {
        return 'Jadwal Pemeriksaan Ditetapkan';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' dijadwalkan pada '.$this->scheduledAt.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    protected function iconColor(): string
    {
        return 'info';
    }

    protected function url(object $notifiable): ?string
    {
        return '/transportir/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Jadwal Pemeriksaan KIM — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Jadwal pemeriksaan kendaraan Anda telah ditetapkan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Jadwal: '.$this->scheduledAt)
            ->action('Buka Pengajuan', url('/transportir/kim-applications'));
    }
}
