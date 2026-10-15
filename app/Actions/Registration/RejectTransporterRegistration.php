<?php

namespace App\Actions\Registration;

use App\Enums\AccountStatus;
use App\Enums\TransporterStatus;
use App\Models\User;
use App\Notifications\TransporterRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectTransporterRegistration
{
    public function execute(User $target, User $reviewer, string $reason): void
    {
        if ($target->account_status !== AccountStatus::Pending) {
            throw ValidationException::withMessages([
                'account_status' => 'Hanya akun berstatus menunggu verifikasi yang dapat ditolak.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($target, $reviewer, $reason) {
            $target->update([
                'account_status' => AccountStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $target->transporters()->update([
                'status' => TransporterStatus::Rejected,
                'rejection_reason' => $reason,
            ]);
        });

        $target->notify(new TransporterRejected($reason));
    }
}
