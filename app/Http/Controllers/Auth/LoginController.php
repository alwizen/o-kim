<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect(Auth::user()->homeUrl());
        }

        return view('landing.login');
    }

    public function store(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Akun Google-only tidak memiliki password sehingga tidak dapat login manual (PRD: jalur terpisah).
        if (! $user || ! $user->password || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectAfterLogin($user);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        if ($user->account_status === AccountStatus::Pending) {
            return redirect()->to($user->homeUrl())->with(
                'success',
                $user->hasRole('transportir')
                    ? null
                    : 'Akun Anda menunggu verifikasi super admin.'
            );
        }

        if ($user->account_status === AccountStatus::Approved) {
            return redirect()->intended($user->homeUrl());
        }

        Auth::logout();

        return redirect('/')->with('error', match ($user->account_status) {
            AccountStatus::Rejected => 'Pendaftaran Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.',
            AccountStatus::Suspended => 'Akun Anda sedang ditangguhkan. Silakan hubungi admin.',
            default => 'Akun Anda tidak aktif.',
        });
    }
}
