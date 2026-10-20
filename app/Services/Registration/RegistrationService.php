<?php

namespace App\Services\Registration;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    /**
     * Role internal yang boleh dipilih mandiri saat pendaftaran.
     * super_admin hanya dapat dibuat via command `okim:make-admin`.
     *
     * @return array<string, string>
     */
    public static function internalRoleOptions(): array
    {
        return collect(InternalRole::cases())
            ->reject(fn (InternalRole $role) => $role === InternalRole::SuperAdmin)
            ->mapWithKeys(fn (InternalRole $role) => [$role->value => $role->label()])
            ->all();
    }

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
     * Buat akun pengguna internal baru berstatus pending dengan role terpilih.
     *
     * Akun harus disetujui super admin (menu Pengguna) sebelum dapat masuk panel.
     */
    public function createPendingInternal(array $attributes, string $role): User
    {
        if (! array_key_exists($role, self::internalRoleOptions())) {
            throw ValidationException::withMessages([
                'role' => 'Role tidak tersedia untuk pendaftaran mandiri.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $role) {
            $user = User::create(array_merge([
                'account_status' => AccountStatus::Pending,
            ], $attributes));

            $user->assignRole($role);

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
