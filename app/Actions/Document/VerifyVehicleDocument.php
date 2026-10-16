<?php

namespace App\Actions\Document;

use App\Enums\DocumentVerificationStatus;
use App\Models\User;
use App\Models\VehicleDocument;
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

        AppNotifier::toTransporter(
            $document->vehicle->transporter_id,
            'Dokumen Kendaraan Diverifikasi',
            ($document->documentType?->name ?? 'Dokumen').' untuk kendaraan '
                .($document->vehicle?->registration_number ?? '-').' telah diverifikasi.',
            'heroicon-o-check-badge',
            'success',
            '/transportir/vehicles/'.$document->vehicle_id.'/edit',
        );
    }
}
