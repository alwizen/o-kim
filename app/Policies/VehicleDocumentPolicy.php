<?php

namespace App\Policies;

use App\Enums\DocumentVerificationStatus;
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

    /**
     * Dokumen terverifikasi bersifat final; perbarui dengan unggah versi baru.
     */
    public function delete(User $user, VehicleDocument $document): bool
    {
        if ($document->verification_status === DocumentVerificationStatus::Verified) {
            return false;
        }

        return $user->transporters()
            ->where('transporters.id', $document->vehicle->transporter_id)
            ->exists();
    }

    /**
     * Dokumen terverifikasi tidak dapat diubah.
     */
    public function update(User $user, VehicleDocument $document): bool
    {
        if ($document->verification_status === DocumentVerificationStatus::Verified) {
            return false;
        }

        return $this->delete($user, $document);
    }

    /**
     * Verifikasi dokumen hanya oleh petugas internal berwenang.
     */
    public function verify(User $user, VehicleDocument $document): bool
    {
        return $user->can('verify_documents');
    }
}
