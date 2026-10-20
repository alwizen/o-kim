<?php

namespace App\Http\Controllers\Auth;

use App\Enums\InternalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InternalRegisterRequest;
use App\Notifications\InternalRegistrationSubmitted;
use App\Services\Registration\RegistrationService;
use App\Support\AppNotifier;
use Illuminate\Support\Facades\Auth;

class InternalRegisterController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect(Auth::user()->homeUrl());
        }

        return view('landing.register-internal', [
            'roleOptions' => RegistrationService::internalRoleOptions(),
        ]);
    }

    public function store(InternalRegisterRequest $request, RegistrationService $registration)
    {
        $user = $registration->createPendingInternal([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ], $request->validated('role'));

        $roleLabel = InternalRole::tryFrom($user->getRoleNames()->first() ?? '')?->label() ?? $user->getRoleNames()->first();

        AppNotifier::notifyStaffWithPermission(
            'manage_users',
            new InternalRegistrationSubmitted($user->name, $roleLabel ?? 'Pengguna Internal'),
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/')
            ->with('success', 'Pendaftaran berhasil. Akun Anda menunggu verifikasi super admin sebelum dapat digunakan.');
    }
}
