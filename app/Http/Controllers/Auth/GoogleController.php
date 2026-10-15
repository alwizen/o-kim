<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, RegistrationService $registration)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect('/')->with('error', 'Autentikasi Google gagal. Silakan coba lagi.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // Jangan gabungkan akun otomatis berdasarkan email (PRD 5.4).
            // Jika email sudah terdaftar lewat jalur lain, minta hubungi admin.
            if ($registration->emailExists($googleUser->getEmail())) {
                return redirect('/')->with('error', 'Email ini sudah terdaftar melalui jalur lain. Silakan masuk menggunakan kata sandi atau hubungi admin.');
            }

            $user = $registration->createPendingTransporter([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Pengguna',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->account_status === AccountStatus::Pending) {
            return redirect()->route('registration.profile.edit');
        }

        if ($user->account_status === AccountStatus::Approved) {
            return redirect()->intended('/transportir');
        }

        Auth::logout();

        return redirect('/')->with('error', match ($user->account_status) {
            AccountStatus::Rejected => 'Pendaftaran Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.',
            AccountStatus::Suspended => 'Akun Anda sedang ditangguhkan. Silakan hubungi admin.',
            default => 'Akun Anda tidak aktif.',
        });
    }
}
