<?php

namespace App\Policies;

use App\Models\InspectionResultAttachment;
use App\Models\User;

class InspectionResultAttachmentPolicy
{
    /**
     * Petugas internal boleh melihat semua; transportir hanya milik perusahaannya.
     */
    public function view(User $user, InspectionResultAttachment $attachment): bool
    {
        if ($user->can('access_admin_panel')) {
            return true;
        }

        $transporterId = $attachment->result?->application?->transporter_id;

        if ($transporterId === null) {
            return false;
        }

        return $user->transporters()
            ->where('transporters.id', $transporterId)
            ->exists();
    }
}
