<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect(Auth::user()->homeUrl());
        }

        return view('landing.register');
    }

    public function store(RegisterRequest $request, RegistrationService $registration)
    {
        $user = $registration->createPendingTransporter([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
        ]);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('registration.profile.edit')
            ->with('success', 'Akun berhasil dibuat. Lengkapi profil perusahaan Anda untuk proses verifikasi.');
    }
}
