<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class InspectionScheduledForStaff extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $scheduledAt,
        public bool $isReschedule = false,
        public ?string $applicationId = null,
    ) {}

    protected function title(): string
    {
        return $this->isReschedule ? 'Jadwal Pemeriksaan Diperbarui' : 'Jadwal Pemeriksaan Ditetapkan';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — NOPOL '.$this->registrationNumber.', jadwal '.$this->scheduledAt.'.';
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
        return $this->applicationId !== null
            ? '/admin/kim-applications/'.$this->applicationId
            : '/admin/kim-applications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->isReschedule ? 'Jadwal Pemeriksaan Diperbarui' : 'Jadwal Pemeriksaan Ditetapkan').' — '.$this->applicationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line($this->isReschedule
                ? 'Jadwal pemeriksaan pengajuan KIM telah diubah.'
                : 'Jadwal pemeriksaan pengajuan KIM telah ditetapkan.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Jadwal: '.$this->scheduledAt)
            ->action('Buka Pengajuan', url($this->url($notifiable)));
    }
}
