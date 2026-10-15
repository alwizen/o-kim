<?php

namespace App\Actions\User;

use App\Enums\AccountStatus;
use App\Models\User;

class ActivateUser
{
    /**
     * Aktifkan kembali akun pengguna internal (status menjadi approved).
     */
    public function execute(User $target): void
    {
        $target->update([
            'account_status' => AccountStatus::Approved,
            'rejection_reason' => null,
        ]);
    }
}
