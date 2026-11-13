<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Document\VehicleDocumentService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Dokumen kendaraan yang sudah terverifikasi bersifat final:
 * tidak dapat dihapus/diubah, harus diganti dengan unggahan versi baru.
 */
class VehicleDocumentLockTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake(config('media.disk'));

        $this->transporter = Transporter::factory()->create();

        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
    }

    private function upload(string $code = 'STNK'): VehicleDocument
    {
        return app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            DocumentType::where('code', $code)->value('id'),
            UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ['document_number' => 'DOC-1', 'expires_at' => '2027-01-01'],
        );
    }

    private function markVerified(VehicleDocument $document): VehicleDocument
    {
        $document->update([
            'verification_status' => DocumentVerificationStatus::Verified,
            'verified_by' => $this->makeSuperAdmin()->id,
            'verified_at' => now(),
        ]);

        return $document->refresh();
    }

    public function test_verified_document_cannot_be_deleted(): void
    {
        $document = $this->markVerified($this->upload());
        $path = $document->file_path;

        // Pastikan berkas benar-benar ada sebelum menguji penolakan hapus.
        Storage::disk(config('media.disk'))->assertExists($path);

        try {
            app(VehicleDocumentService::class)->delete($document);
            $this->fail('Dokumen terverifikasi seharusnya tidak dapat dihapus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        Storage::disk(config('media.disk'))->assertExists($path);
        $this->assertDatabaseHas('vehicle_documents', ['id' => $document->id]);
    }

    public function test_pending_document_can_be_deleted(): void
    {
        $document = $this->upload();
        $path = $document->file_path;

        app(VehicleDocumentService::class)->delete($document);

        Storage::disk(config('media.disk'))->assertMissing($path);
        $this->assertDatabaseMissing('vehicle_documents', ['id' => $document->id]);
    }

    public function test_rejected_document_can_be_deleted(): void
    {
        $document = $this->upload();
        $document->update([
            'verification_status' => DocumentVerificationStatus::Rejected,
            'rejection_reason' => 'Foto buram',
        ]);

        app(VehicleDocumentService::class)->delete($document->refresh());

        $this->assertDatabaseMissing('vehicle_documents', ['id' => $document->id]);
    }

    public function test_policy_blocks_deleting_verified_document_for_owner_and_admin(): void
    {
        $document = $this->markVerified($this->upload());

        $this->assertFalse($this->owner->can('delete', $document));
        $this->assertFalse($this->makeSuperAdmin()->can('delete', $document));
    }

    public function test_policy_blocks_updating_verified_document(): void
    {
        $document = $this->markVerified($this->upload());

        $this->assertFalse($this->owner->can('update', $document));
    }

    public function test_uploading_new_version_supersedes_verified_document(): void
    {
        $verified = $this->markVerified($this->upload());

        $replacement = $this->upload();

        $this->assertSame($replacement->id, $verified->fresh()->replaced_by_id);
        $this->assertSame(DocumentVerificationStatus::Pending, $replacement->fresh()->verification_status);
        $this->assertDatabaseHas('vehicle_documents', ['id' => $verified->id]);
    }
}
