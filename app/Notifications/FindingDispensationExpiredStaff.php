<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class FindingDispensationExpiredStaff extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $kimNumber,
        public string $dueDate,
        public int $openItems,
    ) {}

    protected function title(): string
    {
        return 'KIM Sementara Kedaluwarsa';
    }

    protected function body(object $notifiable): string
    {
        return $this->kimNumber.' ('.$this->registrationNumber.') kedaluwarsa karena dispensasi berakhir '
            .$this->dueDate.'. '.$this->openItems.' temuan menunggu pemeriksaan ulang.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    protected function iconColor(): string
    {
        return 'danger';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/kim-cards';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KIM Sementara Kedaluwarsa — '.$this->kimNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('KIM sementara telah kedaluwarsa karena batas dispensasi terlampaui.')
            ->line('Nomor KIM: '.$this->kimNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Batas dispensasi: '.$this->dueDate)
            ->line('Jumlah temuan yang belum ditutup: '.$this->openItems)
            ->action('Buka Kartu KIM', url('/admin/kim-cards'));
    }
}
