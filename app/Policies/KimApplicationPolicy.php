<?php

namespace App\Policies;

use App\Enums\Department;
use App\Models\KimApplication;
use App\Models\User;

class KimApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_applications') || $user->hasRole('transportir');
    }

    public function view(User $user, KimApplication $model): bool
    {
        if ($user->can('view_applications')) {
            return true;
        }

        // Transportir hanya boleh melihat pengajuan milik perusahaannya.
        return $user->transporters()
            ->where('transporters.id', $model->transporter_id)
            ->exists();
    }

    public function create(User $user): bool
    {
        // Transportir membuat pengajuan dari panel transportir.
        return $user->hasRole('transportir');
    }

    public function update(User $user, KimApplication $model): bool
    {
        // Pengajuan hanya diubah melalui service/action, bukan langsung.
        return false;
    }

    public function delete(User $user, KimApplication $model): bool
    {
        return false;
    }

    /**
     * Petugas hanya boleh memeriksa bagian checklist fungsinya sendiri.
     */
    public function inspect(User $user, KimApplication $model, ?Department $department = null): bool
    {
        if ($department instanceof Department) {
            return $user->can($department->inspectPermission());
        }

        foreach (Department::inspectingDepartments() as $candidate) {
            if ($user->can($candidate->inspectPermission())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Approval per fungsi (termasuk approval final FTM).
     */
    public function approve(User $user, KimApplication $model, ?Department $department = null): bool
    {
        if ($department instanceof Department) {
            return $user->can(
                $department === Department::Ftm ? 'approve_final' : 'approve_'.$department->value
            );
        }

        foreach ([...Department::inspectingDepartments(), Department::Ftm] as $candidate) {
            $permission = $candidate === Department::Ftm ? 'approve_final' : 'approve_'.$candidate->value;

            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
