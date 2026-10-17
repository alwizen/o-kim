<?php

namespace App\Notifications\Channels;

use App\Jobs\SendNotificationMail;
use Illuminate\Notifications\Notification;

class QueuedMailChannel
{
    /**
     * Antrekan pengiriman email agar tidak memblokir request dan
     * kegagalan SMTP tidak menggagalkan proses bisnis.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        SendNotificationMail::dispatch($notifiable, $notification);
    }
}
