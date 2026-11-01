<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pemberitahuan ke pengawas bahwa sebuah NOPOL menerbitkan KIM sementara
 * melalui dispensasi FTM, tanpa melewati approval fungsi pengawas.
 */
class KimCardIssuedAsDispensationStaff extends AppNotification
{
    public function __construct(
        public string $applicationNumber,
        public string $registrationNumber,
        public string $kimNumber,
        public string $expiresAt,
        public string $issuedBy,
    ) {}

    protected function title(): string
    {
        return 'KIM Sementara Diterbitkan';
    }

    protected function body(object $notifiable): string
    {
        return 'NOPOL '.$this->registrationNumber.' menerbitkan KIM sementara '
            .$this->kimNumber.' melalui dispensasi FTM hingga '.$this->expiresAt.'.';
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
        return '/admin/kim-cards';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KIM Sementara Diterbitkan — '.$this->registrationNumber)
            ->greeting('Yth '.$notifiable->name.',')
            ->line('KIM sementara telah diterbitkan melalui dispensasi Fuel Terminal Manager.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Nomor KIM: '.$this->kimNumber)
            ->line('NOPOL: '.$this->registrationNumber)
            ->line('Berlaku hingga: '.$this->expiresAt)
            ->line('Diterbitkan oleh: '.$this->issuedBy)
            ->line('Approval fungsi pengawas tidak diperlukan karena temuan telah didispensasi.')
            ->action('Lihat KIM', url('/admin/kim-cards'));
    }
}
