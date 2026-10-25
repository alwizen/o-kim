<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tautan reset kata sandi untuk akun email + kata sandi.
 *
 * Dikirim langsung (bukan lewat queue) agar pengguna segera menerima tautan
 * dan kegagalan pengiriman dapat dilaporkan pada request yang sama.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset Kata Sandi O-KIM')
            ->greeting('Yth '.$notifiable->name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun O-KIM Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line('Tautan ini berlaku selama '.$expire.' menit dan hanya dapat dipakai satu kali.')
            ->line('Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini. Kata sandi Anda tidak akan berubah.')
            ->salutation('Salam, O-KIM Fuel Terminal Tegal');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
