<?php

namespace App\Policies;

use App\Models\KimCard;
use App\Models\User;

class KimCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_kim_cards') || $user->hasRole('transportir');
    }

    public function view(User $user, KimCard $model): bool
    {
        if ($user->can('view_kim_cards')) {
            return true;
        }

        // Transportir hanya boleh melihat KIM milik perusahaannya.
        return $user->transporters()
            ->where('transporters.id', $model->application->transporter_id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, KimCard $model): bool
    {
        return false;
    }

    public function delete(User $user, KimCard $model): bool
    {
        return false;
    }
}
