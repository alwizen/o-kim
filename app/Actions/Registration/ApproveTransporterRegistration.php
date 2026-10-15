<?php

namespace App\Actions\Registration;

use App\Enums\AccountStatus;
use App\Enums\TransporterStatus;
use App\Models\User;
use App\Notifications\TransporterApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveTransporterRegistration
{
    public function execute(User $target, User $reviewer): void
    {
        if ($target->account_status !== AccountStatus::Pending) {
            throw ValidationException::withMessages([
                'account_status' => 'Hanya akun berstatus menunggu verifikasi yang dapat disetujui.',
            ]);
        }

        DB::transaction(function () use ($target, $reviewer) {
            $target->update([
                'account_status' => AccountStatus::Approved,
                'rejection_reason' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $target->transporters()->update(['status' => TransporterStatus::Approved]);
        });

        $target->notify(new TransporterApproved);
    }
}
