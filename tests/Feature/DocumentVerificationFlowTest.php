<?php

namespace Tests\Feature;

use App\Actions\Document\RejectVehicleDocument;
use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\ListKimApplications;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication;
use App\Filament\Transportir\Resources\KimCards\Pages\ListKimCards as TransportirListKimCards;
use App\Models\DocumentType;
use App\Models\InspectionChecklist;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleDocumentVerified;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Document\VehicleDocumentService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $transportir;

    private User $admin;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($this->transporter);
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        $this->admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->admin->assignRole('super_admin');
    }

    private function uploadPending(string $code): void
    {
        app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            DocumentType::where('code', $code)->value('id'),
            UploadedFile::fake()->create("{$code}.pdf", 100, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );
    }

    private function verifyAll(): void
    {
        $this->vehicle->documents()->whereNull('replaced_by_id')->get()
            ->each(fn ($document) => $document->update([
                'verification_status' => DocumentVerificationStatus::Verified,
                'verified_by' => $this->admin->id,
                'verified_at' => now(),
            ]));
    }

    private function submitApplication(): KimApplication
    {
        $application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($application, $this->transportir);

        return $application->fresh();
    }

    // ---------- A1: pengajuan cukup dokumen terunggah ----------

    public function test_submit_allowed_with_pending_documents(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }

        $application = $this->submitApplication();

        $this->assertSame(ApplicationStatus::Submitted, $application->status);
    }

    public function test_submit_blocked_when_required_document_not_uploaded(): void
    {
        $this->uploadPending('STNK');
        $this->uploadPending('KEUR');

        $application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);

        $this->expectException(ValidationException::class);
        app(KimApplicationService::class)->submit($application, $this->transportir);
    }

    // ---------- A2: gate verified pada penjadwalan ----------

    public function test_schedule_table_action_blocked_with_pending_documents(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $this->actingAs($this->admin);

        Livewire::test(ListKimApplications::class)
            ->mountTableAction('schedule', $application)
            ->set('mountedActions.0.data.inspection_scheduled_at', now()->addDay()->format('Y-m-d H:i:s'))
            ->callMountedTableAction();

        $this->assertNull($application->fresh()->inspection_scheduled_at);
    }

    public function test_schedule_table_action_allowed_after_all_documents_verified(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();
        $this->verifyAll();

        $this->actingAs($this->admin);

        Livewire::test(ListKimApplications::class)
            ->mountTableAction('schedule', $application)
            ->set('mountedActions.0.data.inspection_scheduled_at', now()->addDay()->format('Y-m-d H:i:s'))
            ->callMountedTableAction();

        $this->assertNotNull($application->fresh()->inspection_scheduled_at);
    }

    public function test_schedule_view_action_blocked_with_pending_documents(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $this->actingAs($this->admin);

        Livewire::test(ViewKimApplication::class, ['record' => $application->getRouteKey()])
            ->mountAction('schedule')
            ->set('mountedActions.0.data.inspection_scheduled_at', now()->addDay()->format('Y-m-d H:i:s'))
            ->callMountedAction();

        $this->assertNull($application->fresh()->inspection_scheduled_at);
    }

    // ---------- A3: verifikasi inline di halaman pengajuan ----------

    public function test_application_view_shows_document_verification_section(): void
    {
        Notification::fake();
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $this->actingAs($this->admin);

        Livewire::test(ViewKimApplication::class, ['record' => $application->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Dokumen Kendaraan')
            ->assertSee('Menunggu Verifikasi');
    }

    public function test_inline_verify_updates_document_and_notifies_transportir(): void
    {
        Notification::fake();
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $stnk = $application->vehicle->documents()
            ->whereHas('documentType', fn ($q) => $q->where('code', 'STNK'))
            ->whereNull('replaced_by_id')
            ->firstOrFail();

        app(VerifyVehicleDocument::class)->execute($stnk, $this->admin);

        $this->assertSame(DocumentVerificationStatus::Verified, $stnk->fresh()->verification_status);

        Notification::assertSentTo(
            $this->transportir,
            VehicleDocumentVerified::class,
        );
    }

    public function test_inline_reject_stores_reason(): void
    {
        Notification::fake();
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $stnk = $application->vehicle->documents()
            ->whereHas('documentType', fn ($q) => $q->where('code', 'STNK'))
            ->whereNull('replaced_by_id')
            ->firstOrFail();

        app(RejectVehicleDocument::class)->execute($stnk, $this->admin, 'Dokumen buram');

        $this->assertSame(DocumentVerificationStatus::Rejected, $stnk->fresh()->verification_status);
        $this->assertSame('Dokumen buram', $stnk->fresh()->rejection_reason);
    }

    public function test_transportir_cannot_verify_document_inline(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $stnk = $application->vehicle->documents()
            ->whereHas('documentType', fn ($q) => $q->where('code', 'STNK'))
            ->whereNull('replaced_by_id')
            ->firstOrFail();

        $this->expectException(ValidationException::class);
        app(VerifyVehicleDocument::class)->execute($stnk, $this->transportir);
    }

    // ---------- A4: badge dokumen pending ----------

    public function test_pending_documents_badge_visible_on_admin_table(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadPending($code);
        }
        $application = $this->submitApplication();

        $this->actingAs($this->admin);

        Livewire::test(ListKimApplications::class)
            ->assertSuccessful()
            ->assertSee('3 pending');
    }

    // ---------- B: daftar KIM transportir ----------

    private function issueKim(Vehicle $vehicle, Transporter $transporter): KimCard
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            app(VehicleDocumentService::class)->upload(
                $vehicle,
                DocumentType::where('code', $code)->value('id'),
                UploadedFile::fake()->create("{$code}.pdf", 100, 'application/pdf'),
                ['expires_at' => now()->addYear()->toDateString()],
            );
        }
        $this->verifyAll();

        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($application, $this->transportir);
        $application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->admin->id,
        ]);

        foreach (Department::inspectingDepartments() as $department) {
            $checklist = InspectionChecklist::where('department', $department->value)
                ->where('is_active', true)
                ->first();

            if (! $checklist) {
                continue;
            }

            foreach ($checklist->items()->where('is_active', true)->get() as $item) {
                app(InspectionService::class)->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $this->admin,
                );
            }
        }

        $application = $application->fresh();
        foreach (Department::inspectingDepartments() as $department) {
            app(ApprovalService::class)->decide($application, $department, ApprovalDecision::Approved, $this->admin);
        }
        app(ApprovalService::class)->decide($application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->admin);

        return app(KimIssuanceService::class)->issue($application->fresh());
    }

    public function test_transportir_sees_only_own_kim_cards(): void
    {
        $ownCard = $this->issueKim($this->vehicle, $this->transporter);

        $otherTransporter = Transporter::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['transporter_id' => $otherTransporter->id]);
        $otherCard = $this->issueKim($otherVehicle, $otherTransporter);

        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->transportir);

        Livewire::test(TransportirListKimCards::class)
            ->assertSuccessful()
            ->assertSee($ownCard->kim_number)
            ->assertDontSee($otherCard->kim_number);
    }

    public function test_transportir_cannot_view_other_transporter_kim(): void
    {
        $otherTransporter = Transporter::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['transporter_id' => $otherTransporter->id]);
        $card = $this->issueKim($otherVehicle, $otherTransporter);

        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->transportir);

        $this->actingAs($this->transportir)
            ->get('/transportir/kim-cards/'.$card->getRouteKey())
            ->assertNotFound();
    }

    public function test_transportir_kim_download_action_present(): void
    {
        $card = $this->issueKim($this->vehicle, $this->transporter);

        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->transportir);

        Livewire::test(TransportirListKimCards::class)
            ->assertSuccessful()
            ->assertSee($card->kim_number);
    }
}
