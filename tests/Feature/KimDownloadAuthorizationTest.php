<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SEC-01: unduhan KIM harus mengikuti KimCardPolicy (least privilege).
 */
class KimDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private KimApplication $application;

    private User $owner;

    private array $officers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($transporter);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role === 'ftm' ? ['ftm'] : [$role, 'pengawas_'.$role]);
            $this->officers[$role] = $officer;
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);

        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->officers['ftm']->id,
        ]);

        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', $department->value))->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $this->application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $this->officers[$department->value],
                );
            }
        }

        $approval = app(ApprovalService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $approval->decide(
                $this->application->fresh(),
                $department,
                ApprovalDecision::Approved,
                $this->officers[$department->value],
            );
        }

        $approval->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->officers['ftm'],
        );
    }

    private function issueCard()
    {
        return app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    public function test_tad_officer_without_view_kim_cards_is_forbidden(): void
    {
        $card = $this->issueCard();

        $tad = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $tad->assignRole('hsse');

        // HSSe punya access_admin_panel tetapi tidak punya view_kim_cards.
        $this->assertTrue($tad->can('access_admin_panel'));
        $this->assertFalse($tad->can('view_kim_cards'));

        $this->actingAs($tad)
            ->get(route('kim.download', $card))
            ->assertForbidden();
    }

    public function test_transporter_owner_can_download_own_card(): void
    {
        $card = $this->issueCard();

        $this->actingAs($this->owner)
            ->get(route('kim.download', $card))
            ->assertSuccessful();
    }

    public function test_user_with_view_kim_cards_can_download(): void
    {
        $card = $this->issueCard();

        $ftm = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $ftm->assignRole('ftm');

        $this->assertTrue($ftm->can('view_kim_cards'));

        $this->actingAs($ftm)
            ->get(route('kim.download', $card))
            ->assertSuccessful();
    }

    public function test_other_transporter_cannot_download(): void
    {
        $card = $this->issueCard();

        $otherTransporter = Transporter::factory()->create();
        $intruder = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $intruder->assignRole('transportir');
        $intruder->transporters()->attach($otherTransporter);

        $this->actingAs($intruder)
            ->get(route('kim.download', $card))
            ->assertForbidden();
    }
}
