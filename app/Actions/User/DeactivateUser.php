<?php

namespace App\Actions\User;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeactivateUser
{
    /**
     * Nonaktifkan akun pengguna internal (status menjadi suspended).
     *
     * @throws ValidationException jika akun sendiri atau super admin aktif terakhir
     */
    public function execute(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        if ($this->isLastActiveSuperAdmin($target)) {
            throw ValidationException::withMessages([
                'user' => 'Super Admin aktif terakhir tidak dapat dinonaktifkan.',
            ]);
        }

        $target->update([
            'account_status' => AccountStatus::Suspended,
        ]);
    }

    private function isLastActiveSuperAdmin(User $target): bool
    {
        if (! $target->hasRole(InternalRole::SuperAdmin->value)) {
            return false;
        }

        if ($target->account_status !== AccountStatus::Approved) {
            return false;
        }

        $activeSuperAdmins = User::role(InternalRole::SuperAdmin->value)
            ->where('account_status', AccountStatus::Approved->value)
            ->whereKeyNot($target->getKey())
            ->count();

        return $activeSuperAdmins === 0;
    }
}
