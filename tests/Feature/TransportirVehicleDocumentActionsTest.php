<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DocumentVerificationStatus;
use App\Filament\Transportir\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Transportir\Resources\Vehicles\RelationManagers\DocumentsRelationManager;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Document\VehicleDocumentService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Transportir tidak boleh menghapus kendaraan, dan hanya boleh mengubah/menghapus
 * dokumen kendaraan selama belum diverifikasi admin.
 */
class TransportirVehicleDocumentActionsTest extends TestCase
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

    private function upload(): VehicleDocument
    {
        return app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ['document_number' => 'DOC-1', 'expires_at' => '2027-01-01'],
        );
    }

    private function relationManager()
    {
        return Livewire::actingAs($this->owner)->test(DocumentsRelationManager::class, [
            'ownerRecord' => $this->vehicle,
            'pageClass' => EditVehicle::class,
        ]);
    }

    public function test_transportir_has_no_delete_vehicle_action(): void
    {
        Filament::setCurrentPanel('transportir');

        Livewire::actingAs($this->owner)
            ->test(EditVehicle::class, ['record' => $this->vehicle->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_pending_document_can_be_edited_and_deleted(): void
    {
        $document = $this->upload();

        $this->relationManager()
            ->assertTableActionEnabled('edit', $document)
            ->assertTableActionEnabled('delete', $document);
    }

    public function test_verified_document_edit_and_delete_are_disabled(): void
    {
        $document = $this->upload();
        $document->update(['verification_status' => DocumentVerificationStatus::Verified]);

        $this->relationManager()
            ->assertTableActionDisabled('edit', $document->refresh())
            ->assertTableActionDisabled('delete', $document->refresh());
    }

    public function test_transportir_can_update_pending_document_metadata(): void
    {
        $document = $this->upload();

        app(VehicleDocumentService::class)->update($document, [
            'document_number' => 'DOC-UPDATED',
            'expires_at' => '2028-01-01',
        ]);

        $this->assertSame('DOC-UPDATED', $document->fresh()->document_number);
    }
}
