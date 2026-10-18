<?php

namespace App\Policies;

use App\Models\FindingActionAttachment;
use App\Models\User;

class FindingActionAttachmentPolicy
{
    /**
     * Petugas internal boleh melihat semua; transportir hanya milik perusahaannya.
     */
    public function view(User $user, FindingActionAttachment $attachment): bool
    {
        if ($user->can('access_admin_panel')) {
            return true;
        }

        $transporterId = $attachment->findingAction?->finding?->application?->transporter_id;

        if ($transporterId === null) {
            return false;
        }

        return $user->transporters()
            ->where('transporters.id', $transporterId)
            ->exists();
    }
}
