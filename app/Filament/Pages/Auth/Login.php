<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Halaman login panel admin dengan tampilan dua kolom seperti /masuk.
 *
 * Hanya lapisan visual yang diganti. Seluruh logika autentikasi tetap milik
 * Filament\Auth\Pages\Login: schema form, rate limiting, multi-factor
 * challenge, dan redirect setelah login.
 */
class Login extends BaseLogin
{
    public function getLayout(): string
    {
        return 'components.layout.auth-split';
    }

    public function getHeading(): string
    {
        return 'Masuk ke akun Anda';
    }

    public function getSubheading(): string
    {
        return 'Gunakan email dan kata sandi akun internal Anda.';
    }
}
