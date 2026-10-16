<?php

namespace Tests;

use App\Enums\AccountStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Document\VehicleDocumentService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    /**
     * Unggah dokumen kendaraan berdasarkan kode jenis dokumen.
     */
    protected function uploadVehicleDocument(
        Vehicle $vehicle,
        string $documentTypeCode,
        ?string $expiresAt = null,
        bool $verify = true,
        ?User $verifier = null,
    ): VehicleDocument {
        $document = app(VehicleDocumentService::class)->upload(
            $vehicle,
            DocumentType::where('code', $documentTypeCode)->value('id'),
            UploadedFile::fake()->create("{$documentTypeCode}.pdf", 100, 'application/pdf'),
            ['expires_at' => $expiresAt ?? now()->addYear()->toDateString()],
        );

        if ($verify) {
            $verifier ??= $this->makeSuperAdmin();

            $document->update([
                'verification_status' => DocumentVerificationStatus::Verified,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);
        }

        return $document->refresh();
    }

    /**
     * Buat (atau ambil) akun super admin untuk keperluan verifikasi pada test.
     */
    protected function makeSuperAdmin(): User
    {
        $admin = User::role('super_admin')->first();

        if ($admin) {
            return $admin;
        }

        $admin = User::factory()->create([
            'account_status' => AccountStatus::Approved,
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
