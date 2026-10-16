<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
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

class VehicleDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    private Vehicle $ownVehicle;

    private Vehicle $foreignVehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class]);

        $transporterA = Transporter::factory()->create();
        $transporterB = Transporter::factory()->create();

        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($transporterA);

        $this->otherUser = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->otherUser->assignRole('transportir');
        $this->otherUser->transporters()->attach($transporterB);

        $this->ownVehicle = Vehicle::factory()->create(['transporter_id' => $transporterA->id]);
        $this->foreignVehicle = Vehicle::factory()->create(['transporter_id' => $transporterB->id]);
    }

    private function uploadDocument(Vehicle $vehicle, string $typeCode = 'STNK'): VehicleDocument
    {
        Storage::fake('local');

        return app(VehicleDocumentService::class)->upload(
            $vehicle,
            DocumentType::where('code', $typeCode)->value('id'),
            UploadedFile::fake()->create('stnk.pdf', 100, 'application/pdf'),
            ['document_number' => 'DOC-001', 'expires_at' => '2027-01-01'],
        );
    }

    public function test_registration_number_is_normalized(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => '  g 1234 ab  ']);

        $this->assertSame('G 1234 AB', $vehicle->registration_number);
    }

    public function test_transportir_cannot_view_other_company_vehicle(): void
    {
        $this->actingAs($this->otherUser)
            ->get("/transportir/vehicles/{$this->ownVehicle->id}/edit")
            ->assertNotFound();
    }

    public function test_transportir_cannot_download_other_company_document(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $this->actingAs($this->otherUser)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_owner_can_download_own_document(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $this->actingAs($this->owner)
            ->get(route('documents.download', $document))
            ->assertSuccessful();
    }

    public function test_unauthenticated_user_cannot_download_document(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $this->get(route('documents.download', $document))
            ->assertRedirect();
    }

    public function test_owner_can_preview_own_document_inline(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $response = $this->actingAs($this->owner)
            ->get(route('documents.preview', $document));

        $response->assertSuccessful();

        $this->assertStringContainsString(
            'inline',
            $response->headers->get('content-disposition') ?? ''
        );
    }

    public function test_transportir_cannot_preview_other_company_document(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $this->actingAs($this->otherUser)
            ->get(route('documents.preview', $document))
            ->assertForbidden();
    }

    public function test_admin_can_preview_any_document(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('documents.preview', $document))
            ->assertSuccessful();
    }

    public function test_unauthenticated_user_cannot_preview_document(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        $this->get(route('documents.preview', $document))->assertRedirect();
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        Storage::fake('local');

        $this->expectException(ValidationException::class);

        app(VehicleDocumentService::class)->upload(
            $this->ownVehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
        );
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('local');

        $this->expectException(ValidationException::class);

        app(VehicleDocumentService::class)->upload(
            $this->ownVehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
        );
    }

    public function test_replacing_document_keeps_history(): void
    {
        $first = $this->uploadDocument($this->ownVehicle);
        $second = $this->uploadDocument($this->ownVehicle);

        $this->assertSame($second->id, $first->fresh()->replaced_by_id);
        $this->assertTrue($second->isCurrentVersion());
        $this->assertFalse($first->fresh()->isCurrentVersion());
        $this->assertSame(2, $this->ownVehicle->documents()->count());
    }

    public function test_document_file_stored_in_private_disk_with_generated_name(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);

        Storage::disk('local')->assertExists($document->file_path);
        $this->assertStringNotContainsString('public', $document->file_path);
        $this->assertNotSame('stnk.pdf', basename($document->file_path));
        $this->assertSame('stnk.pdf', $document->original_filename);
    }

    public function test_deleting_document_removes_file(): void
    {
        $document = $this->uploadDocument($this->ownVehicle);
        $path = $document->file_path;

        app(VehicleDocumentService::class)->delete($document);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('vehicle_documents', ['id' => $document->id]);
    }

    public function test_document_types_are_seeded(): void
    {
        foreach (['STNK', 'KEUR', 'TERA', 'PTO'] as $code) {
            $this->assertDatabaseHas('document_types', ['code' => $code]);
        }

        $this->assertTrue(DocumentType::where('code', 'STNK')->first()->is_required);
        $this->assertFalse(DocumentType::where('code', 'PTO')->first()->is_required);
    }
}
