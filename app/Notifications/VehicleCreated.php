<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class VehicleCreated extends AppNotification
{
    public function __construct(
        public string $companyName,
        public string $registrationNumber,
        public ?string $vehicleId = null,
    ) {}

    protected function title(): string
    {
        return 'Kendaraan Baru Ditambahkan';
    }

    protected function body(object $notifiable): string
    {
        return $this->companyName.' menambahkan kendaraan '.$this->registrationNumber.'.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-truck';
    }

    protected function iconColor(): string
    {
        return 'info';
    }

    protected function url(object $notifiable): ?string
    {
        return $this->vehicleId !== null
            ? '/admin/vehicles/'.$this->vehicleId
            : '/admin/vehicles';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kendaraan Baru — O-KIM')
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Transportir menambahkan kendaraan baru.')
            ->line('Perusahaan: '.$this->companyName)
            ->line('NOPOL: '.$this->registrationNumber)
            ->action('Buka Data Kendaraan', url($this->url($notifiable)));
    }
}
