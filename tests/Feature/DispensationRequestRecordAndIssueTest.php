<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DispensationRequestStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\DispensationRequests\DispensationRequestResource;
use App\Models\Finding;
use App\Models\FindingDispensationRequest;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\DispensationService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Riwayat pengajuan KIM sementara tetap tampil setelah diputuskan, dan FTM dapat
 * menerbitkan KIM sementara langsung dari data dispensasi yang disetujui.
 */
class DispensationRequestRecordAndIssueTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $ftm;

    private Vehicle $vehicle;

    private KimApplication $application;

    /** @var array<string, User> */
    private array $officers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');
        Notification::fake();

        $this->transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role === 'ftm' ? ['ftm'] : [$role, 'pengawas_'.$role]);
            $this->officers[$role] = $officer;
        }

        $this->ftm = $this->officers['ftm'];

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);
        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->ftm->id,
        ]);
    }

    private function completeInspectionsWithFinding(): Finding
    {
        $inspection = app(InspectionService::class);
        $finding = null;

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', $department->value))
                ->where('is_active', true)
                ->get();

            foreach ($items as $index => $item) {
                $isFindingTarget = $finding === null && $department === Department::Hsse && $index === 0;

                $inspection->recordResult(
                    $this->application->fresh(),
                    $item,
                    $isFindingTarget ? InspectionResultValue::Finding : InspectionResultValue::Compliant,
                    $this->officers[$department->value],
                    findingDescription: $isFindingTarget ? 'Sparepart pompa harus di-inden' : null,
                );

                if ($isFindingTarget) {
                    $finding = $this->application->fresh()->findings()->latest('id')->firstOrFail();
                }
            }
        }

        return $finding;
    }

    /**
     * Transportir mengajukan dispensasi, lalu FTM menyetujuinya.
     */
    private function approvedDispensationRequest(): FindingDispensationRequest
    {
        $finding = $this->completeInspectionsWithFinding();

        $request = app(DispensationService::class)->request(
            $finding,
            $this->owner,
            'Sparepart pompa harus di-inden, mohon dispensasi.',
        );

        return app(DispensationService::class)->approveRequest(
            $request,
            $this->ftm,
            Carbon::today()->addDays(7),
            'Disetujui, segera lengkapi.',
        );
    }

    public function test_approved_request_remains_visible_in_the_list(): void
    {
        $request = $this->approvedDispensationRequest();

        $ids = DispensationRequestResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($request->id));
    }

    public function test_rejected_request_remains_visible_in_the_list(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        $request = app(DispensationService::class)->request($finding, $this->owner, 'Mohon dispensasi segera.');
        app(DispensationService::class)->rejectRequest($request, $this->ftm, 'Tidak memenuhi kriteria.');

        $ids = DispensationRequestResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($request->id));
    }

    public function test_pending_request_is_still_listed(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        $request = app(DispensationService::class)->request($finding, $this->owner, 'Mohon dispensasi segera.');

        $ids = DispensationRequestResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($request->id));
    }

    public function test_approval_records_decider_and_due_date_for_reporting(): void
    {
        $request = $this->approvedDispensationRequest()->fresh();

        $this->assertSame(DispensationRequestStatus::Approved, $request->status);
        $this->assertSame($this->ftm->id, $request->decided_by);
        $this->assertNotNull($request->decided_at);
        $this->assertNotNull($request->due_date);
        $this->assertSame('Disetujui, segera lengkapi.', $request->decision_notes);
    }

    public function test_issue_provisional_records_ftm_approval_and_issues_card(): void
    {
        $request = $this->approvedDispensationRequest();
        $application = $this->application->fresh();

        $this->assertSame(ApplicationStatus::Inspection, $application->status);
        $this->assertTrue($application->canIssueProvisional());

        $card = app(KimIssuanceService::class)->issueProvisional($application, $this->ftm);

        $this->assertTrue($card->is_dispensation);
        $this->assertSame(ApplicationStatus::Issued, $this->application->fresh()->status);

        $this->assertDatabaseHas('approvals', [
            'kim_application_id' => $this->application->id,
            'department' => Department::Ftm->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
    }

    public function test_issue_provisional_is_idempotent_when_ftm_already_approved(): void
    {
        $this->approvedDispensationRequest();

        // Approve FTM lebih dulu, lalu terbitkan.
        app(\App\Services\Approval\ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        $card = app(KimIssuanceService::class)->issueProvisional($this->application->fresh(), $this->ftm);

        $this->assertSame(1, $this->application->fresh()->approvals()
            ->where('department', Department::Ftm->value)
            ->where('decision', ApprovalDecision::Approved->value)
            ->count());

        $this->assertTrue($card->is_dispensation);
    }

    public function test_issue_provisional_rejected_without_active_dispensation(): void
    {
        // Selesaikan temuan tanpa dispensasi: tidak ada dispensasi aktif.
        $finding = $this->completeInspectionsWithFinding();
        $action = app(\App\Services\Finding\FindingService::class)
            ->submitAction($finding, $this->owner, 'Sparepart sudah diganti.');
        app(\App\Services\Finding\FindingService::class)
            ->verifyAction($finding->fresh(), $action, $this->officers['hsse'], true);

        $this->expectException(ValidationException::class);

        app(KimIssuanceService::class)->issueProvisional($this->application->fresh(), $this->ftm);
    }

    public function test_can_issue_provisional_is_false_once_card_exists(): void
    {
        $this->approvedDispensationRequest();
        app(KimIssuanceService::class)->issueProvisional($this->application->fresh(), $this->ftm);

        $this->assertFalse($this->application->fresh()->canIssueProvisional());
    }

    public function test_can_issue_provisional_is_false_without_dispensation(): void
    {
        $this->completeInspectionsWithFinding();

        $this->assertFalse($this->application->fresh()->canIssueProvisional());
    }
}
