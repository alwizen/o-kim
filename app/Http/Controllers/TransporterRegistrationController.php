<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Transporter;
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

        if ($user->account_status !== AccountStatus::Pending) {
            return redirect('/')->with('error', 'Akun Anda sudah tidak dalam status pendaftaran.');
        }

        $transporter = $user->transporters()->first();

        return view('landing.registration', compact('transporter'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->account_status !== AccountStatus::Pending) {
            return redirect('/')->with('error', 'Akun Anda sudah tidak dalam status pendaftaran.');
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

        return redirect()->route('registration.profile.edit')
            ->with('success', 'Data perusahaan berhasil disimpan. Menunggu verifikasi admin.');
    }
}
