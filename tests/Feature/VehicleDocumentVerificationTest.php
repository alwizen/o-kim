<?php

namespace Tests\Feature;

use App\Actions\Document\RejectVehicleDocument;
use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\DocumentVerificationStatus;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\DocumentsRelationManager;
use App\Filament\Transportir\Resources\KimApplications\Pages\CreateKimApplication;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\VehicleDocumentRejected;
use App\Services\Application\KimApplicationService;
use App\Services\Document\VehicleDocumentService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $transportir;

    private User $admin;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($this->transporter);

        $this->admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->admin->assignRole('super_admin');

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
    }

    private function uploadPending(string $code): VehicleDocument
    {
        return app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            DocumentType::where('code', $code)->value('id'),
            UploadedFile::fake()->create("{$code}.pdf", 100, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );
    }

    // ---------- Action ----------

    public function test_admin_can_verify_document(): void
    {
        $document = $this->uploadPending('STNK');

        app(VerifyVehicleDocument::class)->execute($document, $this->admin);

        $document->refresh();
        $this->assertSame(DocumentVerificationStatus::Verified, $document->verification_status);
        $this->assertSame($this->admin->id, $document->verified_by);
        $this->assertNotNull($document->verified_at);
        $this->assertNull($document->rejection_reason);
    }

    public function test_admin_can_reject_document_with_reason(): void
    {
        Notification::fake();
        $document = $this->uploadPending('STNK');

        app(RejectVehicleDocument::class)->execute($document, $this->admin, 'Foto tidak jelas');

        $document->refresh();
        $this->assertSame(DocumentVerificationStatus::Rejected, $document->verification_status);
        $this->assertSame('Foto tidak jelas', $document->rejection_reason);
        Notification::assertSentTo($this->transportir, VehicleDocumentRejected::class);
    }

    public function test_reject_requires_reason(): void
    {
        $document = $this->uploadPending('STNK');

        $this->expectException(ValidationException::class);
        app(RejectVehicleDocument::class)->execute($document, $this->admin, '');
    }

    public function test_replaced_document_cannot_be_verified(): void
    {
        $old = $this->uploadPending('STNK');
        $new = $this->uploadPending('STNK');

        $this->assertSame($new->id, $old->fresh()->replaced_by_id);

        $this->expectException(ValidationException::class);
        app(VerifyVehicleDocument::class)->execute($old->fresh(), $this->admin);
    }

    // ---------- Policy / UI ----------

    public function test_transportir_cannot_verify_own_document(): void
    {
        $document = $this->uploadPending('STNK');

        $this->assertFalse(
            Gate::forUser($this->transportir)->allows('verify', $document)
        );
    }

    public function test_admin_panel_staff_can_verify(): void
    {
        $hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $hsse->assignRole('hsse');

        $document = $this->uploadPending('STNK');

        $this->assertTrue(
            Gate::forUser($hsse)->allows('verify', $document)
        );
    }

    public function test_verify_action_works_via_relation_manager(): void
    {
        $document = $this->uploadPending('STNK');

        $this->actingAs($this->admin);

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $this->vehicle,
            'pageClass' => ViewVehicle::class,
        ])
            ->callTableAction('verify', $document)
            ->assertHasNoTableActionErrors();

        $this->assertSame(DocumentVerificationStatus::Verified, $document->fresh()->verification_status);
    }

    public function test_reject_action_works_via_relation_manager(): void
    {
        Notification::fake();
        $document = $this->uploadPending('STNK');

        $this->actingAs($this->admin);

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $this->vehicle,
            'pageClass' => ViewVehicle::class,
        ])
            ->callTableAction('reject', $document, data: ['reason' => 'Dokumen buram'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(DocumentVerificationStatus::Rejected, $document->fresh()->verification_status);
    }

    // ---------- Integrasi aturan ----------

    public function test_submit_blocked_when_documents_not_verified(): void
    {
        // Unggah tanpa verifikasi.
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }

        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        $this->expectException(ValidationException::class);
        app(KimApplicationService::class)->submit($application, $this->transportir);
    }

    public function test_submit_allowed_after_documents_verified(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, verifier: $this->admin);
        }

        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        app(KimApplicationService::class)->submit($application, $this->transportir);

        $this->assertSame(
            ApplicationStatus::Submitted,
            $application->fresh()->status
        );
    }

    public function test_vehicle_table_shows_pending_documents_count(): void
    {
        $this->uploadPending('STNK');
        $this->uploadVehicleDocument($this->vehicle, 'KEUR', verifier: $this->admin);

        $this->actingAs($this->admin)
            ->get('/admin/vehicles')
            ->assertSuccessful();
    }

    // ---------- Dropdown kendaraan pada pengajuan ----------

    private function openCreateApplicationPage()
    {
        Filament::setCurrentPanel(
            Filament::getPanel('transportir')
        );

        return Livewire::test(
            CreateKimApplication::class
        );
    }

    public function test_vehicle_with_all_required_documents_verified_is_selectable(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, verifier: $this->admin);
        }

        $this->actingAs($this->transportir);

        $options = app(KimApplicationService::class)
            ->readyVehiclesQuery([$this->transporter->id])
            ->pluck('registration_number')
            ->all();

        $this->assertContains($this->vehicle->registration_number, $options);
    }

    public function test_vehicle_with_unverified_document_is_not_selectable(): void
    {
        // STNK & KEUR terverifikasi, TERA masih pending.
        $this->uploadVehicleDocument($this->vehicle, 'STNK', verifier: $this->admin);
        $this->uploadVehicleDocument($this->vehicle, 'KEUR', verifier: $this->admin);
        $this->uploadPending('TERA');

        $options = app(KimApplicationService::class)
            ->readyVehiclesQuery([$this->transporter->id])
            ->pluck('registration_number')
            ->all();

        $this->assertNotContains($this->vehicle->registration_number, $options);
    }

    public function test_vehicle_missing_required_document_is_not_selectable(): void
    {
        // Hanya STNK & KEUR; TERA belum diunggah.
        $this->uploadVehicleDocument($this->vehicle, 'STNK', verifier: $this->admin);
        $this->uploadVehicleDocument($this->vehicle, 'KEUR', verifier: $this->admin);

        $options = app(KimApplicationService::class)
            ->readyVehiclesQuery([$this->transporter->id])
            ->pluck('registration_number')
            ->all();

        $this->assertNotContains($this->vehicle->registration_number, $options);
    }

    public function test_vehicle_with_rejected_document_is_not_selectable(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, verifier: $this->admin);
        }

        // Tolak satu dokumen → kendaraan keluar dari daftar siap.
        $stnk = $this->vehicle->documents()
            ->whereHas('documentType', fn ($q) => $q->where('code', 'STNK'))
            ->firstOrFail();
        app(RejectVehicleDocument::class)->execute($stnk, $this->admin, 'Tidak jelas');

        $options = app(KimApplicationService::class)
            ->readyVehiclesQuery([$this->transporter->id])
            ->pluck('registration_number')
            ->all();

        $this->assertNotContains($this->vehicle->registration_number, $options);
    }

    public function test_vehicle_with_open_application_is_not_selectable(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, verifier: $this->admin);
        }

        app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);

        $options = app(KimApplicationService::class)
            ->readyVehiclesQuery([$this->transporter->id])
            ->pluck('registration_number')
            ->all();

        $this->assertNotContains($this->vehicle->registration_number, $options);
    }

    public function test_create_page_dropdown_excludes_unverified_vehicle(): void
    {
        $this->uploadPending('STNK');
        $this->uploadPending('KEUR');
        $this->uploadPending('TERA');

        $this->actingAs($this->transportir);

        $this->openCreateApplicationPage()
            ->assertFormFieldExists('vehicle_id')
            ->assertFormSet(['vehicle_id' => null]);
    }

    public function test_create_page_dropdown_lists_verified_vehicle(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, verifier: $this->admin);
        }

        $this->actingAs($this->transportir);

        $options = app(KimApplicationService::class)
            ->readyVehiclesQuery([$this->transporter->id])
            ->pluck('registration_number', 'id')
            ->all();

        $this->assertSame([$this->vehicle->id => $this->vehicle->registration_number], $options);
    }

    public function test_backend_rejects_creation_when_document_unverified(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }

        $this->actingAs($this->transportir);

        $this->openCreateApplicationPage()
            ->fillForm([
                'vehicle_id' => $this->vehicle->id,
                'application_type' => ApplicationType::New->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['vehicle_id']);

        $this->assertDatabaseCount('kim_applications', 0);
    }
}
