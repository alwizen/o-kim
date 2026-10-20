<?php

namespace App\Actions\Document;

use App\Enums\DocumentVerificationStatus;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Notifications\VehicleDocumentRejected;
use Illuminate\Validation\ValidationException;

class RejectVehicleDocument
{
    /**
     * Tolak dokumen kendaraan dengan alasan.
     */
    public function execute(VehicleDocument $document, User $verifier, string $reason): void
    {
        if (! $verifier->can('verify', $document)) {
            throw ValidationException::withMessages([
                'verifier' => 'Anda tidak berwenang menolak dokumen ini.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        if ($document->replaced_by_id !== null) {
            throw ValidationException::withMessages([
                'document' => 'Dokumen ini sudah digantikan versi terbaru dan tidak dapat ditolak.',
            ]);
        }

        $document->update([
            'verification_status' => DocumentVerificationStatus::Rejected,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->notifyOwner($document, $reason);
    }

    private function notifyOwner(VehicleDocument $document, string $reason): void
    {
        $owner = $document->vehicle?->transporter?->users()->first();

        $owner?->notify(new VehicleDocumentRejected(
            $document->documentType?->name ?? 'Dokumen',
            $document->vehicle?->registration_number ?? '-',
            $reason,
        ));
    }
}
