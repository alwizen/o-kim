<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Transporter;
use App\Models\User;
use App\Notifications\TransporterRegistered;
use App\Notifications\TransporterRegistrationSubmitted;
use App\Support\AppNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransporterRegistrationController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        $this->ensurePendingTransporter($user);

        if ($user->account_status !== AccountStatus::Pending) {
            return redirect('/')->with('error', 'Akun Anda sudah tidak dalam status pendaftaran.');
        }

        $transporter = $user->transporters()->first();

        // Profil yang sudah dikirim dikunci; perubahan hanya melalui admin.
        if ($transporter?->profile_submitted_at !== null) {
            return redirect()->route('registration.pending.transporter');
        }

        return view('landing.registration', compact('transporter'));
    }

    /**
     * Halaman tunggu setelah profil perusahaan dikirim.
     */
    public function pending(Request $request)
    {
        $user = $request->user();

        $this->ensurePendingTransporter($user);

        if ($user->account_status !== AccountStatus::Pending) {
            return redirect('/')->with('error', 'Akun Anda sudah tidak dalam status pendaftaran.');
        }

        return view('landing.pending-transporter', [
            'transporter' => $user->transporters()->first(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $this->ensurePendingTransporter($user);

        if ($user->account_status !== AccountStatus::Pending) {
            return redirect('/')->with('error', 'Akun Anda sudah tidak dalam status pendaftaran.');
        }

        // Cegah pengiriman ulang setelah profil masuk antrean verifikasi.
        if ($user->transporters()->first()?->profile_submitted_at !== null) {
            return redirect()->route('registration.pending.transporter');
        }

        $validated = $request->validate([
            'pic_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['required', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:30'],
            'legal_identifier' => ['nullable', 'string', 'max:255'],
        ]);

        $isFirstSubmission = DB::transaction(function () use ($user, $validated) {
            $user->update(['name' => $validated['pic_name']]);

            $transporterData = collect($validated)->except('pic_name')->all();

            $transporter = $user->transporters()->first();
            $isFirstSubmission = $transporter === null;

            if ($transporter) {
                $transporter->update($transporterData);
            } else {
                $transporter = Transporter::create($transporterData);
                $user->transporters()->attach($transporter->id);
            }

            if ($transporter->profile_submitted_at === null) {
                $transporter->update(['profile_submitted_at' => now()]);
            }

            return $isFirstSubmission;
        });

        // Notifikasi hanya sekali agar penyimpanan ulang profil tidak mengirim email berulang.
        if ($isFirstSubmission) {
            $user->notify(new TransporterRegistered);

            AppNotifier::notifyStaffWithPermission(
                'review_transporter_registrations',
                new TransporterRegistrationSubmitted(
                    (string) $validated['company_name'],
                    (string) $validated['pic_name'],
                ),
            );
        }

        return redirect()->route('registration.pending.transporter')
            ->with('success', 'Data perusahaan berhasil dikirim. Menunggu verifikasi admin.');
    }

    /**
     * Hanya akun transportir yang boleh memakai alur pendaftaran ini.
     * Akun internal juga berstatus Pending, sehingga pemeriksaan status saja tidak cukup.
     */
    private function ensurePendingTransporter(?User $user): void
    {
        abort_if($user === null || ! $user->hasRole('transportir'), 403, 'Halaman ini hanya untuk pendaftaran transportir.');
    }
}
