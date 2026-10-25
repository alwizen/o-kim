<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Ringkasan harian dokumen & KIM yang kedaluwarsa / akan kedaluwarsa
 * untuk petugas internal, dikelompokkan per perusahaan.
 */
class ExpiryReminderStaff extends AppNotification
{
    /**
     * @param  array<int, array{company: string, label: string, nopol: string, expires_at: string, days_left: int}>  $items
     */
    public function __construct(
        public array $items,
        public int $thresholdDays,
    ) {}

    protected function title(): string
    {
        return 'Ringkasan Dokumen Akan Kedaluwarsa';
    }

    protected function body(object $notifiable): string
    {
        return count($this->items).' dokumen/KIM mendekati atau sudah kedaluwarsa.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    protected function iconColor(): string
    {
        return 'warning';
    }

    protected function url(object $notifiable): ?string
    {
        return '/admin/vehicles';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Ringkasan Dokumen Akan Kedaluwarsa — O-KIM')
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Berikut dokumen/KIM yang sudah kedaluwarsa atau akan habis dalam '
                .$this->thresholdDays.' hari.');

        foreach ($this->items as $item) {
            $message->line($item['company'].' — '.$item['label'].' (NOPOL '.$item['nopol'].', '
                .$item['expires_at'].', '.$this->daysLabel($item['days_left']).').');
        }

        return $message
            ->action('Buka Data Kendaraan', url('/admin/vehicles'))
            ->line('Email ini dikirim otomatis setiap hari selama masih ada dokumen/KIM yang perlu diperbarui.');
    }

    private function daysLabel(int $days): string
    {
        return match (true) {
            $days < 0 => 'lewat '.abs($days).' hari',
            $days === 0 => 'hari ini',
            default => $days.' hari lagi',
        };
    }
}
