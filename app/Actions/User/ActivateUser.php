<?php

namespace App\Actions\User;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\InternalAccountApproved;

class ActivateUser
{
    /**
     * Setujui/aktifkan akun pengguna internal (status menjadi approved).
     */
    public function execute(User $target): void
    {
        $wasPending = $target->account_status === AccountStatus::Pending;

        $target->update([
            'account_status' => AccountStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($wasPending) {
            $target->notify(new InternalAccountApproved);
        }
    }
}
