<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Digest harian dokumen & KIM yang kedaluwarsa / akan kedaluwarsa,
 * dikirim ke PIC perusahaan transportir.
 */
class ExpiryReminder extends AppNotification
{
    /**
     * @param  array<int, array{label: string, nopol: string, expires_at: string, days_left: int}>  $items
     */
    public function __construct(
        public array $items,
        public int $thresholdDays,
        public ?int $transporterId = null,
    ) {}

    protected function title(): string
    {
        return 'Pengingat Kedaluwarsa';
    }

    protected function body(object $notifiable): string
    {
        return count($this->items).' dokumen/KIM perlu diperbarui.';
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
        return '/transportir/vehicles';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Pengingat Kedaluwarsa Dokumen & KIM — O-KIM')
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Berikut dokumen/KIM yang sudah kedaluwarsa atau akan habis dalam '
                .$this->thresholdDays.' hari. Mohon segera diperbarui.');

        foreach ($this->items as $item) {
            $message->line($this->formatItem($item));
        }

        return $message
            ->action('Perbarui Dokumen', url('/transportir/vehicles'))
            ->line('Email ini dikirim otomatis setiap hari selama masih ada dokumen/KIM yang perlu diperbarui.');
    }

    /**
     * @param  array{label: string, nopol: string, expires_at: string, days_left: int}  $item
     */
    private function formatItem(array $item): string
    {
        return $item['label'].' — NOPOL '.$item['nopol'].', berlaku hingga '.$item['expires_at'].' ('.$this->daysLabel($item['days_left']).').';
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
