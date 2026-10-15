<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('manage_users') && $this->isInternal($model);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('manage_users') && $this->isInternal($model);
    }

    /**
     * Akun tidak dihapus permanen — gunakan nonaktifkan (status suspended).
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function isInternal(User $model): bool
    {
        return ! $model->hasRole('transportir');
    }
}
