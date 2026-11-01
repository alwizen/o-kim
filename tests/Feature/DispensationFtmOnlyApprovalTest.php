<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\KimCardIssued;
use App\Notifications\KimCardIssuedAsDispensationStaff;
use App\Services\Application\KimApplicationService;
use App\Services\Application\VehicleProgressService;
use App\Services\Approval\ApprovalService;
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
use Tests\TestCase;

/**
 * KIM sementara (ada temuan dispensasi aktif) cukup disetujui FTM.
 * Pengawas fungsi hanya menerima pemberitahuan, tidak perlu menyetujui.
 */
class DispensationFtmOnlyApprovalTest extends TestCase
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

    /**
     * Isi seluruh checklist sebagai Sesuai, dengan satu temuan di HSSE.
     */
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

    private function dispensation(): DispensationService
    {
        return app(DispensationService::class);
    }

    private function grantActiveDispensation(): Finding
    {
        $finding = $this->completeInspectionsWithFinding();

        $this->dispensation()->grant(
            $finding,
            $this->ftm,
            Carbon::today()->addDays(7),
            'Sparepart pompa harus di-inden.',
        );

        return $finding->fresh();
    }

    public function test_application_with_active_dispensation_is_detected(): void
    {
        $this->grantActiveDispensation();

        $this->assertTrue($this->application->fresh()->hasActiveDispensation());
    }

    public function test_application_without_dispensation_is_not_flagged(): void
    {
        $this->completeInspectionsWithFinding();

        $this->assertFalse($this->application->fresh()->hasActiveDispensation());
    }

    public function test_ftm_can_approve_without_functional_approvals_when_dispensed(): void
    {
        $this->grantActiveDispensation();
        $this->application->update(['status' => ApplicationStatus::PendingApproval]);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        $this->assertDatabaseHas('approvals', [
            'kim_application_id' => $this->application->id,
            'department' => Department::Ftm->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
    }

    public function test_ftm_still_requires_functional_approvals_without_dispensation(): void
    {
        // Tanpa dispensasi, temuan biasa tidak boleh lolos ke FTM.
        $this->completeInspectionsWithFinding();
        $this->application->update(['status' => ApplicationStatus::PendingApproval]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );
    }

    public function test_kim_can_be_issued_without_functional_approvals_when_dispensed(): void
    {
        $this->grantActiveDispensation();
        $this->application->update(['status' => ApplicationStatus::PendingApproval]);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->assertTrue($card->is_dispensation);
    }

    public function test_issuance_still_requires_functional_approvals_without_dispensation(): void
    {
        $this->completeInspectionsWithFinding();
        $this->application->update(['status' => ApplicationStatus::Approved]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    public function test_supervisors_are_notified_when_provisional_kim_issued(): void
    {
        $this->grantActiveDispensation();
        $this->application->update(['status' => ApplicationStatus::PendingApproval]);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        app(KimIssuanceService::class)->issue($this->application->fresh());

        foreach (['pengawas_hsse', 'pengawas_distribusi', 'pengawas_qq'] as $role) {
            $supervisor = User::role($role)->first();
            $this->assertNotNull($supervisor, 'Pengawas '.$role.' tidak ditemukan.');
            Notification::assertSentTo($supervisor, KimCardIssuedAsDispensationStaff::class);
        }
    }

    public function test_transporter_still_notified_in_addition_to_supervisors(): void
    {
        $this->grantActiveDispensation();
        $this->application->update(['status' => ApplicationStatus::PendingApproval]);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        app(KimIssuanceService::class)->issue($this->application->fresh());

        Notification::assertSentTo($this->owner, KimCardIssued::class);
    }

    public function test_supervisor_steps_show_not_required_when_dispensed(): void
    {
        $this->grantActiveDispensation();

        $steps = app(VehicleProgressService::class)->stepsFor($this->application->fresh());

        $byKey = collect($steps)->keyBy('key');

        foreach (Department::inspectingDepartments() as $department) {
            $this->assertStringContainsString(
                'Tidak diperlukan',
                $byKey[$department->value]['description'],
            );
        }

        $this->assertSame('active', $byKey['ftm']['state']);
    }
}
