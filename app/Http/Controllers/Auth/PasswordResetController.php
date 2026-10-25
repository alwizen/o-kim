<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function showRequest(): View
    {
        return view('landing.forgot-password');
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $user = User::where('email', $email)->first();

        // Akun Google tidak memiliki kata sandi sehingga tidak dapat direset.
        // Tidak ada pesan khusus di sini: respons harus seragam agar keberadaan
        // akun tidak dapat ditebak (user enumeration).
        if ($user && $user->canResetPassword()) {
            try {
                Password::sendResetLink(['email' => $email]);
            } catch (Throwable $e) {
                report($e);

                // Hapus token agar pengguna dapat langsung mencoba lagi tanpa menunggu throttle.
                $broker = config('auth.defaults.passwords', 'users');

                DB::table(config("auth.passwords.{$broker}.table", 'password_reset_tokens'))
                    ->where('email', $email)
                    ->delete();
            }
        }

        // Pesan seragam agar keberadaan akun tidak mudah ditebak dari respons.
        return back()->with(
            'status',
            'Jika email tersebut terdaftar dan menggunakan kata sandi, tautan reset telah kami kirim. Periksa kotak masuk dan folder spam.'
        );
    }

    public function showReset(Request $request, string $token): View
    {
        return view('landing.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $this->messageFor($status)]);
        }

        return redirect($this->loginUrlFor((string) $request->validated('email')))
            ->with('status', 'Kata sandi berhasil diubah. Silakan masuk menggunakan kata sandi baru Anda.');
    }

    private function messageFor(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN, Password::INVALID_USER => 'Tautan reset kata sandi tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
            Password::RESET_THROTTLED => 'Silakan tunggu sebentar sebelum mencoba lagi.',
            default => 'Kata sandi gagal diubah. Silakan coba lagi.',
        };
    }

    /**
     * Petugas internal dikembalikan ke halaman login panel admin,
     * transportir ke halaman masuk publik.
     */
    private function loginUrlFor(string $email): string
    {
        $user = User::where('email', $email)->first();

        if ($user
            && $user->account_status === AccountStatus::Approved
            && ! $user->hasRole('transportir')
            && $user->can('access_admin_panel')) {
            return '/admin/login';
        }

        return '/masuk';
    }
}
