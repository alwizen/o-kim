<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Transporter;
use App\Notifications\TransporterRegistered;
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
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['required', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:30'],
            'legal_identifier' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $transporter = $user->transporters()->first();

            if ($transporter) {
                $transporter->update($validated);
            } else {
                $transporter = Transporter::create($validated);
                $user->transporters()->attach($transporter->id);
            }
        });

        $user->notify(new TransporterRegistered);

        return redirect()->route('registration.profile.edit')
            ->with('success', 'Data perusahaan berhasil disimpan. Menunggu verifikasi admin.');
    }
}
