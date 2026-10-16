<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $transportir;

    private Vehicle $vehicle;

    private KimApplication $application;

    private User $hsseOfficer;

    private User $distribusiOfficer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);

        $transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($transporter);
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        $this->hsseOfficer = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsseOfficer->assignRole('hsse');

        $this->distribusiOfficer = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->distribusiOfficer->assignRole('distribusi');

        $this->application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);
    }

    private function uploadRequiredDocuments(): void
    {
        Storage::fake('local');

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }
    }

    /**
     * Tetapkan jadwal pemeriksaan (prasyarat sebelum checklist dapat diisi).
     */
    private function scheduleInspection(): void
    {
        $this->application->fresh()->update([
            'inspection_scheduled_at' => now()->addDay(),
            'inspection_scheduled_by' => $this->hsseOfficer->id,
        ]);
    }

    /**
     * Kirim pengajuan + tetapkan jadwal pemeriksaan.
     */
    private function submitAndSchedule(): void
    {
        $this->uploadRequiredDocuments();
        app(KimApplicationService::class)->submit($this->application, $this->transportir);
        $this->scheduleInspection();
    }

    private function hsseItem(): InspectionItem
    {
        return InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('item_code', 'HSSE-01')->firstOrFail();
    }

    public function test_draft_cannot_be_submitted_without_required_documents(): void
    {
        $this->expectException(ValidationException::class);

        app(KimApplicationService::class)->submit($this->application, $this->transportir);
    }

    public function test_draft_can_be_submitted_with_complete_documents(): void
    {
        $this->submitAndSchedule();

        $this->assertSame(ApplicationStatus::Submitted, $this->application->fresh()->status);
        $this->assertNotNull($this->application->fresh()->submitted_at);
    }

    public function test_vehicle_cannot_have_two_open_applications(): void
    {
        $this->expectException(ValidationException::class);

        app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
    }

    public function test_officer_cannot_inspect_other_department(): void
    {
        $this->submitAndSchedule();

        $this->expectException(ValidationException::class);

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Compliant,
            $this->distribusiOfficer,
        );
    }

    public function test_finding_result_creates_finding_and_sets_revision_status(): void
    {
        $this->submitAndSchedule();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Finding,
            $this->hsseOfficer,
            findingDescription: 'APAR kedaluwarsa'
        );

        $this->assertSame(ApplicationStatus::RevisionRequired, $this->application->fresh()->status);
        $this->assertDatabaseHas('findings', [
            'kim_application_id' => $this->application->id,
            'department' => 'hsse',
            'status' => FindingStatus::Open->value,
        ]);
    }

    public function test_finding_requires_description(): void
    {
        $this->submitAndSchedule();

        $this->expectException(ValidationException::class);

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Finding,
            $this->hsseOfficer,
        );
    }

    public function test_finding_action_cannot_be_submitted_by_other_company(): void
    {
        $this->submitAndSchedule();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Finding,
            $this->hsseOfficer,
            findingDescription: 'Temuan'
        );

        $finding = $this->application->fresh()->findings->first();

        $outsider = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $outsider->assignRole('transportir');
        $outsider->transporters()->attach(Transporter::factory()->create());

        $this->expectException(ValidationException::class);

        app(FindingService::class)->submitAction($finding, $outsider, 'Perbaikan saya');
    }

    public function test_finding_full_cycle_open_to_verified(): void
    {
        $this->submitAndSchedule();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Finding,
            $this->hsseOfficer,
            findingDescription: 'APAR kedaluwarsa'
        );

        $finding = $this->application->fresh()->findings->first();
        $findingService = app(FindingService::class);

        Storage::fake('local');
        $action = $findingService->submitAction(
            $finding,
            $this->transportir,
            'APAR sudah diganti baru',
            [UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg')]
        );

        $this->assertSame(FindingStatus::SubmittedForVerification, $finding->fresh()->status);
        $this->assertSame(1, $action->attachments()->count());

        $findingService->verifyAction($finding->fresh(), $action, $this->hsseOfficer, accepted: true);

        $this->assertSame(FindingStatus::Verified, $finding->fresh()->status);
        $this->assertNotNull($finding->fresh()->closed_at);
        $this->assertSame(ApplicationStatus::Inspection, $this->application->fresh()->status);
    }

    public function test_rejected_verification_reopens_finding_with_notes(): void
    {
        $this->submitAndSchedule();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Finding,
            $this->hsseOfficer,
            findingDescription: 'Temuan'
        );

        $finding = $this->application->fresh()->findings->first();
        $findingService = app(FindingService::class);

        Storage::fake('local');
        $action = $findingService->submitAction($finding, $this->transportir, 'Sudah diperbaiki');

        $this->expectException(ValidationException::class);
        $findingService->verifyAction($finding->fresh(), $action, $this->hsseOfficer, accepted: false);
    }

    public function test_application_not_ready_for_approval_with_open_finding(): void
    {
        $this->submitAndSchedule();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Finding,
            $this->hsseOfficer,
            findingDescription: 'Temuan'
        );

        $this->assertFalse(app(InspectionService::class)->isReadyForApproval($this->application->fresh()));
    }

    public function test_application_ready_for_approval_when_all_departments_complete(): void
    {
        $this->submitAndSchedule();

        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($department->value);

            $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', $department->value))->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $this->application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $officer,
                );
            }
        }

        $this->assertTrue($inspection->isReadyForApproval($this->application->fresh()));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(KimApplicationService::class)->transitionTo(
            $this->application,
            ApplicationStatus::Issued
        );
    }
}
