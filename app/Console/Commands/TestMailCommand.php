<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailCommand extends Command
{
    protected $signature = 'okim:test-mail {email : Alamat tujuan uji}';

    protected $description = 'Mengirim email uji untuk memverifikasi konfigurasi SMTP O-KIM';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Alamat email tidak valid: '.$email);

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $host = config("mail.mailers.{$mailer}.host");
        $from = config('mail.from.address');

        $this->components->info('Mailer: '.$mailer.($host ? " ({$host})" : ''));
        $this->components->info('Pengirim: '.$from);

        if ($mailer === 'log') {
            $this->components->warn('MAIL_MAILER masih "log" — email hanya dicatat di storage/logs/laravel.log.');
        }

        $body = "Email uji dari O-KIM.\n\n"
            .'Waktu: '.now()->translatedFormat('d M Y H:i:s')."\n"
            .'Mailer: '.$mailer."\n\n"
            .'Jika Anda menerima email ini, konfigurasi SMTP sudah benar.';

        try {
            Mail::raw($body, function ($message) use ($email): void {
                $message->to($email)->subject('Uji SMTP O-KIM — Fuel Terminal Tegal');
            });
        } catch (Throwable $e) {
            $this->components->error('Gagal mengirim email: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Email uji berhasil diproses untuk '.$email.'.');

        return self::SUCCESS;
    }
}
