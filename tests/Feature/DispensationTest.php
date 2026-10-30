<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DispensationAction;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Enums\KimCardStatus;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\FindingDispensationExpired;
use App\Notifications\FindingDispensationGranted;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Finding\DispensationService;
use App\Services\Finding\FindingService;
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
 * Dispensasi temuan oleh FTM + penerbitan KIM sementara.
 *
 * @see docs/future.md
 */
class DispensationTest extends TestCase
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
     * Isi seluruh checklist sebagai Sesuai, lalu buat satu temuan di fungsi HSSE.
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

    private function tomorrow(): Carbon
    {
        return Carbon::today()->addDay();
    }

    // ---------- Otorisasi ----------

    public function test_only_ftm_may_grant_dispensation(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        foreach (['hsse', 'distribusi', 'qq'] as $role) {
            try {
                $this->dispensation()->grant($finding, $this->officers[$role], $this->tomorrow(), 'Sparepart harus di-inden.');
                $this->fail("Role {$role} tidak boleh memberi dispensasi.");
            } catch (ValidationException) {
                // sesuai harapan
            }
        }

        $this->assertSame(FindingStatus::Open, $finding->fresh()->status);
    }

    public function test_super_admin_without_ftm_role_cannot_dispense(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->assertFalse($admin->can('dispensate_findings'));

        $this->expectException(ValidationException::class);
        $this->dispensation()->grant($finding, $admin, $this->tomorrow(), 'Sparepart harus di-inden.');
    }

    public function test_permission_without_ftm_role_is_not_enough(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        $pengawas = $this->officers['hsse'];
        $pengawas->givePermissionTo('dispensate_findings');

        $this->expectException(ValidationException::class);
        $this->dispensation()->grant($finding, $pengawas, $this->tomorrow(), 'Sparepart harus di-inden.');
    }

    // ---------- Grant ----------

    public function test_ftm_can_grant_dispensation_with_due_date_and_reason(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $due = $this->tomorrow()->addDays(6);

        $this->dispensation()->grant($finding, $this->ftm, $due, 'Sparepart pompa harus di-inden.');

        $finding->refresh();

        $this->assertSame(FindingStatus::Dispensation, $finding->status);
        $this->assertSame($due->toDateString(), $finding->dispensation_due_date->toDateString());
        $this->assertSame('Sparepart pompa harus di-inden.', $finding->dispensation_reason);
        $this->assertSame($this->ftm->id, $finding->dispensated_by);
        $this->assertNotNull($finding->dispensated_at);

        $this->assertDatabaseHas('finding_dispensations', [
            'finding_id' => $finding->id,
            'user_id' => $this->ftm->id,
            'action' => DispensationAction::Granted->value,
        ]);

        Notification::assertSentTo($this->owner, FindingDispensationGranted::class);
    }

    public function test_due_date_must_be_in_the_future(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        $this->expectException(ValidationException::class);
        $this->dispensation()->grant($finding, $this->ftm, Carbon::today(), 'Sparepart pompa harus di-inden.');
    }

    public function test_reason_is_required(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        $this->expectException(ValidationException::class);
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'pendek');
    }

    public function test_verified_finding_cannot_be_dispensed(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        app(FindingService::class)->submitAction($finding, $this->owner, 'Sudah diperbaiki');
        $action = $finding->fresh()->actions()->latest('id')->firstOrFail();
        app(FindingService::class)->verifyAction($finding->fresh(), $action, $this->officers['hsse'], accepted: true);

        $this->assertSame(FindingStatus::Verified, $finding->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->dispensation()->grant($finding->fresh(), $this->ftm, $this->tomorrow(), 'Sparepart harus di-inden.');
    }

    // ---------- Extend / ubah alasan / cabut ----------

    public function test_ftm_can_extend_dispensation(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        $newDue = Carbon::today()->addDays(10);
        $this->dispensation()->extend($finding->fresh(), $this->ftm, $newDue, 'Inden terlambat datang.');

        $finding->refresh();

        $this->assertSame($newDue->toDateString(), $finding->dispensation_due_date->toDateString());
        $this->assertDatabaseHas('finding_dispensations', [
            'finding_id' => $finding->id,
            'action' => DispensationAction::Extended->value,
        ]);
    }

    public function test_expired_dispensation_cannot_be_extended(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        $this->travel(5)->days();

        $this->expectException(ValidationException::class);
        $this->dispensation()->extend($finding->fresh(), $this->ftm, Carbon::today()->addDays(3));
    }

    public function test_ftm_can_update_reason(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        $this->dispensation()->updateReason($finding->fresh(), $this->ftm, 'Menunggu approval anggaran pembelian.', 'Revisi alasan');

        $this->assertSame('Menunggu approval anggaran pembelian.', $finding->fresh()->dispensation_reason);
        $this->assertDatabaseHas('finding_dispensations', [
            'finding_id' => $finding->id,
            'action' => DispensationAction::ReasonUpdated->value,
        ]);
    }

    public function test_ftm_can_revoke_dispensation(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        $this->dispensation()->revoke($finding->fresh(), $this->ftm, 'Perbaikan selesai lebih cepat');

        $finding->refresh();

        $this->assertSame(FindingStatus::Open, $finding->status);
        $this->assertNull($finding->dispensation_due_date);
        $this->assertNull($finding->dispensation_reason);
        $this->assertDatabaseHas('finding_dispensations', [
            'finding_id' => $finding->id,
            'action' => DispensationAction::Revoked->value,
        ]);
    }

    public function test_transporter_can_submit_action_on_dispensed_finding(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        app(FindingService::class)->submitAction($finding->fresh(), $this->owner, 'Sparepart sudah diganti', []);

        $this->assertSame(FindingStatus::SubmittedForVerification, $finding->fresh()->status);
    }

    // ---------- Approval ----------

    public function test_dispensation_does_not_block_approval(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        $this->assertTrue(app(InspectionService::class)->isReadyForApproval($this->application->fresh()));

        $approval = app(ApprovalService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $approval->decide($this->application->fresh(), $department, ApprovalDecision::Approved, $this->officers[$department->value]);
        }

        $this->assertSame(ApplicationStatus::PendingApproval, $this->application->fresh()->status);

        $approval->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->ftm);

        $this->assertSame(ApplicationStatus::Approved, $this->application->fresh()->status);
    }

    public function test_expired_dispensation_blocks_approval(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');

        $this->travel(3)->days();

        $this->assertTrue($finding->fresh()->isDispensationExpired());
        $this->assertFalse(app(InspectionService::class)->isReadyForApproval($this->application->fresh()));

        $this->expectException(ValidationException::class);
        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Hsse,
            ApprovalDecision::Approved,
            $this->officers['hsse'],
        );
    }

    // ---------- Penerbitan KIM ----------

    private function approveEverything(): void
    {
        $approval = app(ApprovalService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $approval->decide($this->application->fresh(), $department, ApprovalDecision::Approved, $this->officers[$department->value]);
        }

        $approval->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->ftm);
    }

    public function test_kim_issued_as_provisional_card_capped_by_dispensation_due_date(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $due = Carbon::today()->addDays(3);
        $this->dispensation()->grant($finding, $this->ftm, $due, 'Sparepart pompa harus di-inden.');

        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->assertTrue($card->is_dispensation);
        $this->assertSame($due->toDateString(), $card->dispensation_expires_at->toDateString());
        $this->assertSame($due->toDateString(), $card->expires_at->toDateString());
        $this->assertSame(KimCardStatus::Active, $card->currentStatus());
    }

    public function test_earliest_dispensation_due_date_wins(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        // Temuan kedua pada fungsi Distribusi.
        $distItem = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'distribusi'))
            ->where('is_active', true)
            ->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $distItem,
            InspectionResultValue::Finding,
            $this->officers['distribusi'],
            findingDescription: 'Selang bocor',
        );

        $second = $this->application->fresh()->findings()->where('department', 'distribusi')->firstOrFail();

        $this->dispensation()->grant($finding, $this->ftm, Carbon::today()->addDays(10), 'Sparepart pompa harus di-inden.');
        $this->dispensation()->grant($second, $this->ftm, Carbon::today()->addDays(2), 'Selang bocor menunggu kiriman.');

        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->assertSame(Carbon::today()->addDays(2)->toDateString(), $card->dispensation_expires_at->toDateString());
        $this->assertSame(Carbon::today()->addDays(2)->toDateString(), $card->expires_at->toDateString());
    }

    public function test_kim_without_dispensation_is_not_provisional(): void
    {
        $this->completeInspectionsWithFinding();

        // Tutup temuan secara normal.
        $finding = $this->application->fresh()->findings()->firstOrFail();
        app(FindingService::class)->submitAction($finding, $this->owner, 'Sudah diperbaiki');
        app(FindingService::class)->verifyAction(
            $finding->fresh(),
            $finding->fresh()->actions()->latest('id')->firstOrFail(),
            $this->officers['hsse'],
            accepted: true,
        );

        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->assertFalse($card->is_dispensation);
        $this->assertNull($card->dispensation_expires_at);
    }

    public function test_revoking_dispensation_revokes_provisional_kim(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, Carbon::today()->addDays(3), 'Sparepart pompa harus di-inden.');
        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->dispensation()->revoke($finding->fresh(), $this->ftm, 'Temuan terbukti kritis');

        $this->assertSame(KimCardStatus::Revoked, $card->fresh()->status);
        $this->assertSame(KimCardStatus::Revoked, $card->fresh()->currentStatus());
    }

    public function test_kim_issuance_rejected_when_dispensation_expired(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, $this->tomorrow(), 'Sparepart pompa harus di-inden.');
        $this->approveEverything();

        $this->travel(3)->days();

        $this->expectException(ValidationException::class);
        app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    public function test_command_marks_provisional_kim_as_expired_once(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, Carbon::today()->addDays(2), 'Sparepart pompa harus di-inden.');
        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->travel(4)->days();

        $this->assertSame(KimCardStatus::Expired, $card->fresh()->currentStatus());

        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->assertNotNull($card->fresh()->dispensation_lapsed_at);
        Notification::assertSentTo($this->owner, FindingDispensationExpired::class);

        // Idempotent: jalan kedua tidak menandai ulang.
        $lapsedAt = $card->fresh()->dispensation_lapsed_at;
        $this->travel(1)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->assertTrue($lapsedAt->equalTo($card->fresh()->dispensation_lapsed_at));
    }

    public function test_public_validation_page_shows_provisional_badge_and_lapses(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, Carbon::today()->addDays(2), 'Sparepart pompa harus di-inden.');
        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->get(route('kim.validate', $card->qr_token))
            ->assertSuccessful()
            ->assertSee('Sementara (Dispensasi)')
            ->assertSee('Aktif');

        $this->travel(4)->days();

        $this->get(route('kim.validate', $card->qr_token))
            ->assertSuccessful()
            ->assertSee('Kedaluwarsa');
    }

    public function test_command_skips_revoked_cards(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, Carbon::today()->addDays(2), 'Sparepart pompa harus di-inden.');
        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());
        $this->dispensation()->revoke($finding->fresh(), $this->ftm, 'Temuan kritis');

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->assertNull($card->fresh()->dispensation_lapsed_at);
        $this->assertSame(KimCardStatus::Revoked, $card->fresh()->currentStatus());
    }

    public function test_dry_run_does_not_change_data(): void
    {
        $finding = $this->completeInspectionsWithFinding();
        $this->dispensation()->grant($finding, $this->ftm, Carbon::today()->addDays(2), 'Sparepart pompa harus di-inden.');
        $this->approveEverything();

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->travel(4)->days();

        $this->artisan('okim:expire-dispensations', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($card->fresh()->dispensation_lapsed_at);
    }
}
