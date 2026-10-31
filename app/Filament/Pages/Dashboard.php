<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Dashboard dengan judul "Dashboard" dan subjudul berisi nama akun serta jabatan.
 *
 * Judul bawaan Filament mengikuti locale (`Dasbor`), sehingga di-override di sini
 * agar konsisten memakai istilah "Dashboard".
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getSubheading(): string | Htmlable | null
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $jabatan = filled($user->jabatan) ? $user->jabatan : null;

        $identity = $jabatan === null
            ? $user->name
            : $user->name.' — '.$jabatan;

        return 'Selamat datang, '.$identity;
    }
}
