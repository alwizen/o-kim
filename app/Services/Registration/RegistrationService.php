<?php

namespace App\Services\Registration;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    /**
     * Buat akun transportir baru berstatus pending.
     *
     * Akun tidak pernah langsung mendapat akses operasional: status awal selalu pending
     * dan wajib disetujui admin (PRD 5.1, 7.1).
     */
    public function createPendingTransporter(array $attributes): User
    {
        return DB::transaction(function () use ($attributes) {
            $user = User::create(array_merge([
                'account_status' => AccountStatus::Pending,
            ], $attributes));

            $user->assignRole('transportir');

            return $user;
        });
    }

    /**
     * Apakah email sudah terpakai? (cegah tabrakan antar jalur pendaftaran)
     */
    public function emailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
}
