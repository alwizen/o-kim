<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aksi approval dan penerbitan KIM harus menyegarkan halaman sehingga tombol
 * yang sudah tidak berlaku langsung hilang tanpa reload manual.
 */
class ApprovalActionRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $transportir;

    private Vehicle $vehicle;

    private KimApplication $application;

    private User $hsseOfficer;

    private User $distribusiOfficer;

    private User $qqOfficer;

    private User $hssePengawas;

    private User $distribusiPengawas;

    private User $qqPengawas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Filament::setCurrentPanel('admin');
        Storage::fake('local');

        $transporter = Transporter::factory()->create();

        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($transporter);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        $this->hsseOfficer = $this->officer(['hsse']);
        $this->distribusiOfficer = $this->officer(['distribusi']);
        $this->qqOfficer = $this->officer(['qq']);
        $this->hssePengawas = $this->officer(['pengawas_hsse']);
        $this->distribusiPengawas = $this->officer(['pengawas_distribusi']);
        $this->qqPengawas = $this->officer(['pengawas_qq']);

        $this->application = $this->submitAndCompleteInspection();
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function officer(array $roles): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($roles);

        return $user;
    }

    private function submitAndCompleteInspection(): KimApplication
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }

        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        app(KimApplicationService::class)->submit($application, $this->transportir);

        $application->fresh()->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->hsseOfficer->id,
        ]);

        $officers = [
            Department::Hsse->value => $this->hsseOfficer,
            Department::Distribusi->value => $this->distribusiOfficer,
            Department::Qq->value => $this->qqOfficer,
        ];

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas(
                'checklist',
                fn ($query) => $query->where('department', $department->value),
            )->get();

            foreach ($items as $item) {
                app(InspectionService::class)->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $officers[$department->value],
                );
            }
        }

        return $application->fresh();
    }

    public function test_approval_action_redirects_back_to_view_page(): void
    {
        $this->actingAs($this->hssePengawas);

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->mountAction('approve_hsse')
            ->callMountedAction()
            ->assertRedirect(route('filament.admin.resources.kim-applications.view', [
                'record' => $this->application->getRouteKey(),
            ]));
    }

    public function test_ftm_button_appears_only_after_rsd_approval_and_page_refresh(): void
    {
        $approvals = app(ApprovalService::class);

        $approvals->decide($this->application->fresh(), Department::Hsse, ApprovalDecision::Approved, $this->hssePengawas);
        $approvals->decide($this->application->fresh(), Department::Distribusi, ApprovalDecision::Approved, $this->distribusiPengawas);
        $approvals->decide($this->application->fresh(), Department::Qq, ApprovalDecision::Approved, $this->qqPengawas);

        $ftm = $this->officer(['ftm']);

        // Before RSD approval, FTM button is hidden even on a fresh page.
        $this->actingAs($ftm);

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('approve_ftm');

        $rsd = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $rsd->givePermissionTo(['access_admin_panel', 'view_applications', 'approve_rsd']);

        $approvals->decide($this->application->fresh(), Department::Rsd, ApprovalDecision::Approved, $rsd);

        // After RSD approval and a fresh render (what the redirect produces), FTM is visible.
        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionVisible('approve_ftm');
    }

    public function test_approved_department_button_is_hidden_after_refresh(): void
    {
        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Hsse,
            ApprovalDecision::Approved,
            $this->hssePengawas,
        );

        $this->actingAs($this->hssePengawas);

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('approve_hsse');
    }

    public function test_issued_kim_makes_issue_action_hidden_after_refresh(): void
    {
        $approvals = app(ApprovalService::class);

        $approvals->decide($this->application->fresh(), Department::Hsse, ApprovalDecision::Approved, $this->hssePengawas);
        $approvals->decide($this->application->fresh(), Department::Distribusi, ApprovalDecision::Approved, $this->distribusiPengawas);
        $approvals->decide($this->application->fresh(), Department::Qq, ApprovalDecision::Approved, $this->qqPengawas);

        $rsd = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $rsd->givePermissionTo(['access_admin_panel', 'view_applications', 'approve_rsd']);
        $approvals->decide($this->application->fresh(), Department::Rsd, ApprovalDecision::Approved, $rsd);

        $ftm = $this->officer(['ftm']);
        $approvals->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $ftm);

        $this->assertSame(ApplicationStatus::Approved, $this->application->fresh()->status);

        app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->actingAs($ftm);

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('issue_kim');
    }
}
