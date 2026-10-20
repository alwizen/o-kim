<?php

namespace App\Actions\User;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Models\User;
use App\Notifications\InternalAccountRejected;
use Illuminate\Validation\ValidationException;

class RejectInternalRegistration
{
    /**
     * Tolak pendaftaran pengguna internal yang masih pending, dengan alasan.
     *
     * @throws ValidationException jika akun bukan internal pending atau alasan kosong
     */
    public function execute(User $target, User $actor, string $reason): void
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        if ($target->account_status !== AccountStatus::Pending || ! $this->isInternal($target)) {
            throw ValidationException::withMessages([
                'user' => 'Hanya pendaftaran internal yang menunggu verifikasi dapat ditolak.',
            ]);
        }

        $target->update([
            'account_status' => AccountStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $target->notify(new InternalAccountRejected($reason));
    }

    private function isInternal(User $user): bool
    {
        return $user->hasRole(collect(InternalRole::cases())->map->value->all());
    }
}
