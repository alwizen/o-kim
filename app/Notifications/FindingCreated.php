<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class FindingCreated extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $departmentLabel,
        public string $description,
    ) {}

    protected function title(): string
    {
        return 'Temuan Pemeriksaan Baru';
    }

    protected function body(object $notifiable): string
    {
        return $this->applicationNumber.' — '.$this->departmentLabel.': '.$this->description;
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
        return '/transportir/findings';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Temuan Pemeriksaan — '.$this->applicationNumber)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pemeriksaan menemukan hal yang perlu ditindaklanjuti.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Fungsi: '.$this->departmentLabel)
            ->line('Temuan: '.$this->description)
            ->action('Buka Temuan', url('/transportir/findings'));
    }
}
