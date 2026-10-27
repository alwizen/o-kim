<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Cukup percobaan untuk melewati gangguan transport singkat
     * (mis. relay SMTP memutus koneksi idle atau API mailer timeout)
     * tanpa menahan antrean terlalu lama.
     */
    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [15, 60, 120, 300];

    /**
     * Batas exception nyata; retry tidak berhenti hanya karena satu kali
     * kegagalan transport.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public object $notifiable,
        public Notification $notification,
    ) {}

    public function handle(MailChannel $mailChannel): void
    {
        $mailChannel->send($this->notifiable, $this->notification);
    }

    /**
     * Catat kegagalan permanen agar email yang hilang tidak hanya terlihat
     * di tabel failed_jobs.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Notifikasi email gagal dikirim', [
            'notification' => $this->notification::class,
            'notifiable_type' => $this->notifiable::class,
            'notifiable_id' => $this->notifiable->id ?? null,
            'notifiable_email' => $this->notifiable->email ?? null,
            'error' => $exception?->getMessage(),
        ]);
    }
}
