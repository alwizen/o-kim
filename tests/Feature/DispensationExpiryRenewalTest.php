<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Transportir\Resources\KimApplications\Pages\CreateKimApplication;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\FindingDispensationExpired;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setelah dispensasi habis, KIM sementara tidak berlaku dan transportir
 * mengajukan ulang lewat pengajuan Perpanjangan baru.
 */
class DispensationExpiryRenewalTest extends TestCase
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

        foreach (['hsse', 'distribusi', 'qq', 'ftm', 'rsd'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);

            if ($role === 'rsd') {
                $officer->givePermissionTo('approve_rsd');
            } else {
                $officer->assignRole($role === 'ftm' ? ['ftm'] : [$role, 'pengawas_'.$role]);
            }

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
     * Terbitkan KIM sementara yang batas dispensasinya beberapa hari lagi.
     */
    private function issueProvisionalCard(int $days = 2): KimApplication
    {
        $finding = $this->completeInspectionsWithFinding();

        app(DispensationService::class)->grant(
            $finding,
            $this->ftm,
            Carbon::today()->addDays($days),
            'Sparepart pompa harus di-inden.',
        );

        $this->application->update(['status' => ApplicationStatus::PendingApproval]);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        app(KimIssuanceService::class)->issue($this->application->fresh());

        return $this->application->fresh();
    }

    public function test_expired_dispensation_marks_finding_as_expired(): void
    {
        $application = $this->issueProvisionalCard();

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->assertSame(
            FindingStatus::Expired,
            $application->findings()->firstOrFail()->status,
        );
    }

    public function test_expired_finding_is_not_counted_as_active_dispensation(): void
    {
        $application = $this->issueProvisionalCard();

        $this->assertTrue($application->hasActiveDispensation());

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->assertFalse($application->fresh()->hasActiveDispensation());
    }

    public function test_transporter_is_invited_to_create_renewal(): void
    {
        $this->issueProvisionalCard();

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        Notification::assertSentTo($this->owner, FindingDispensationExpired::class);
    }

    public function test_application_with_lapsed_provisional_card_can_be_renewed(): void
    {
        $application = $this->issueProvisionalCard();

        $this->assertFalse($application->canBeRenewed());

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->assertTrue($application->fresh()->canBeRenewed());
    }

    public function test_application_with_regular_kim_cannot_be_renewed_this_way(): void
    {
        $finding = $this->completeInspectionsWithFinding();

        // Selesaikan temuan tanpa dispensasi agar KIM terbit normal.
        $action = app(FindingService::class)
            ->submitAction($finding, $this->owner, 'Sparepart sudah diganti.');

        app(FindingService::class)
            ->verifyAction($finding->fresh(), $action, $this->officers['hsse'], true);

        foreach (Department::inspectingDepartments() as $department) {
            app(ApprovalService::class)->decide(
                $this->application->fresh(),
                $department,
                ApprovalDecision::Approved,
                $this->officers[$department->value],
            );
        }

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Rsd,
            ApprovalDecision::Approved,
            $this->officers['rsd'],
        );

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->assertFalse($card->is_dispensation);
        $this->assertFalse($this->application->fresh()->canBeRenewed());
    }

    public function test_renewal_draft_can_be_created_after_kim_lapsed(): void
    {
        $application = $this->issueProvisionalCard();

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $renewal = app(KimApplicationService::class)
            ->createDraft($this->vehicle->fresh(), ApplicationType::Renewal);

        $this->assertSame(ApplicationStatus::Draft, $renewal->status);
        $this->assertSame(ApplicationType::Renewal, $renewal->application_type);
        $this->assertNotSame($application->id, $renewal->id);
    }

    public function test_renewal_is_blocked_while_an_open_application_exists(): void
    {
        $this->issueProvisionalCard();
        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        app(KimApplicationService::class)->createDraft($this->vehicle->fresh(), ApplicationType::Renewal);

        $this->expectException(ValidationException::class);

        app(KimApplicationService::class)->createDraft($this->vehicle->fresh(), ApplicationType::Renewal);
    }

    public function test_create_page_prefills_vehicle_and_type_from_query(): void
    {
        $application = $this->issueProvisionalCard();

        $this->travel(4)->days();
        $this->artisan('okim:expire-dispensations')->assertSuccessful();

        $this->actingAs($this->owner);

        Livewire::withQueryParams([
            'vehicle_id' => $application->vehicle_id,
            'application_type' => ApplicationType::Renewal->value,
        ])->test(CreateKimApplication::class)
            ->assertFormSet([
                'vehicle_id' => $application->vehicle_id,
                'application_type' => ApplicationType::Renewal->value,
            ]);
    }

    public function test_expired_status_is_labelled_and_coloured(): void
    {
        $this->assertSame('Dispensasi Kedaluwarsa', FindingStatus::Expired->label());
        $this->assertSame('danger', FindingStatus::Expired->color());
        $this->assertFalse(FindingStatus::Expired->isResolved());
    }
}
