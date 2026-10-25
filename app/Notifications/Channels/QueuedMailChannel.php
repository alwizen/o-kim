<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationEvent;
use App\Jobs\SendNotificationMail;
use App\Services\Notification\NotificationSettingsService;
use Illuminate\Notifications\Notification;

class QueuedMailChannel
{
    public function __construct(
        private NotificationSettingsService $settings,
    ) {}

    /**
     * Antrekan pengiriman email agar tidak memblokir request dan
     * kegagalan SMTP tidak menggagalkan proses bisnis.
     *
     * Email difilter berdasarkan pengaturan super admin (event aktif + role penerima).
     * Notifikasi lonceng panel tidak terpengaruh karena memakai channel "database".
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $event = NotificationEvent::tryFor($notification);

        if ($event !== null && ! $event->locked()) {
            if (! $this->settings->mailEnabled($event)) {
                return;
            }

            if (! $this->settings->notifiableHasRole($event, $notifiable)) {
                return;
            }
        }

        SendNotificationMail::dispatch($notifiable, $notification);
    }
}
