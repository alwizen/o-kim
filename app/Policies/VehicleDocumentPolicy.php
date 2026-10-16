<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleDocument;

class VehicleDocumentPolicy
{
    public function view(User $user, VehicleDocument $document): bool
    {
        if ($user->can('access_admin_panel')) {
            return true;
        }

        return $user->transporters()
            ->where('transporters.id', $document->vehicle->transporter_id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('transportir');
    }

    public function delete(User $user, VehicleDocument $document): bool
    {
        return $user->transporters()
            ->where('transporters.id', $document->vehicle->transporter_id)
            ->exists();
    }

    /**
     * Verifikasi dokumen hanya oleh petugas internal berwenang.
     */
    public function verify(User $user, VehicleDocument $document): bool
    {
        return $user->can('verify_documents');
    }
}
