<?php

namespace App\Actions\Document;

use App\Enums\DocumentVerificationStatus;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Notifications\VehicleDocumentVerified;
use App\Support\AppNotifier;
use Illuminate\Validation\ValidationException;

class VerifyVehicleDocument
{
    /**
     * Tandai dokumen kendaraan sebagai terverifikasi.
     */
    public function execute(VehicleDocument $document, User $verifier): void
    {
        if ($document->replaced_by_id !== null) {
            throw ValidationException::withMessages([
                'document' => 'Dokumen ini sudah digantikan versi terbaru dan tidak dapat diverifikasi.',
            ]);
        }

        $document->update([
            'verification_status' => DocumentVerificationStatus::Verified,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        AppNotifier::notifyTransporter(
            $document->vehicle->transporter_id,
            new VehicleDocumentVerified(
                $document->documentType?->name ?? 'Dokumen',
                $document->vehicle?->registration_number ?? '-',
            ),
        );
    }
}
