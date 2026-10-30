<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\DispensationRequestStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\Findings\Pages\ViewFinding as AdminViewFinding;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication as AdminViewKimApplication;
use App\Filament\Transportir\Resources\Findings\Pages\ViewFinding as TransportirViewFinding;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication as TransportirViewKimApplication;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\DispensationService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bagian UI Filament untuk dispensasi temuan & KIM sementara.
 */
class DispensationUiTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $ftm;

    private User $admin;

    private User $hsse;

    private Vehicle $vehicle;

    private KimApplication $application;

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

        $this->ftm = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->ftm->assignRole('ftm');

        $this->admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->admin->assignRole('super_admin');

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole(['hsse', 'pengawas_hsse']);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        $this->application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subHour(),
            'inspection_scheduled_by' => $this->hsse->id,
        ]);
    }

    private function makeFinding(): Finding
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('is_active', true)
            ->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'Sparepart pompa harus di-inden',
        );

        return $this->application->fresh()->findings()->latest('id')->firstOrFail();
    }

    public function test_grant_action_is_no_longer_available_on_finding_page(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->ftm);
        Filament::setCurrentPanel('admin');

        // Pemberian dispensasi kini hanya melalui menu "Pengajuan KIM Sementara".
        Livewire::test(AdminViewFinding::class, ['record' => $finding->getRouteKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('dispensation_grant');
    }

    public function test_super_admin_does_not_see_grant_action(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminViewFinding::class, ['record' => $finding->getRouteKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('dispensation_grant');
    }

    public function test_dispensation_granted_via_request_flow_from_finding_page(): void
    {
        $finding = $this->makeFinding();
        $due = Carbon::today()->addDays(5);

        $request = app(DispensationService::class)->request(
            $finding,
            $this->owner,
            'Sparepart pompa harus di-inden.',
        );

        app(DispensationService::class)->approveRequest($request, $this->ftm, $due);

        $this->assertSame(FindingStatus::Dispensation, $finding->fresh()->status);
        $this->assertSame(DispensationRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_invalid_approval_date_is_rejected(): void
    {
        $finding = $this->makeFinding();

        $request = app(DispensationService::class)->request(
            $finding,
            $this->owner,
            'Sparepart pompa harus di-inden.',
        );

        $this->expectException(ValidationException::class);

        app(DispensationService::class)->approveRequest($request, $this->ftm, Carbon::today());
    }

    public function test_dispensation_section_is_visible_after_grant(): void
    {
        $finding = $this->makeFinding();
        app(DispensationService::class)->grant($finding, $this->ftm, Carbon::today()->addDays(3), 'Sparepart harus di-inden.');

        $this->actingAs($this->ftm);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminViewFinding::class, ['record' => $finding->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Dispensasi FTM')
            ->assertSee('Sparepart harus di-inden.')
            ->assertActionVisible('dispensation_extend')
            ->assertActionVisible('dispensation_revoke');
    }

    public function test_transportir_sees_dispensation_info_read_only(): void
    {
        $finding = $this->makeFinding();
        app(DispensationService::class)->grant($finding, $this->ftm, Carbon::today()->addDays(3), 'Sparepart harus di-inden.');

        $this->actingAs($this->owner);
        Filament::setCurrentPanel('transportir');

        Livewire::test(TransportirViewFinding::class, ['record' => $finding->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Dispensasi FTM')
            ->assertSee('Sparepart harus di-inden.')
            ->assertActionVisible('submit_action')
            ->assertDontSee('Beri Dispensasi (FTM)')
            ->assertDontSee('Cabut Dispensasi');
    }

    public function test_application_page_no_longer_offers_manual_grant_action(): void
    {
        $this->makeFinding();

        $this->actingAs($this->ftm);
        Filament::setCurrentPanel('admin');

        // Dispensasi kini hanya diberikan lewat menu "Pengajuan KIM Sementara".
        Livewire::test(AdminViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('dispensation_grant');
    }

    public function test_application_page_hides_grant_action_when_no_dispensable_findings(): void
    {
        $this->actingAs($this->ftm);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('dispensation_grant');
    }

    /**
     * Regresi: badge status temuan pada halaman pengajuan transportir harus
     * tetap tampil untuk SEMUA status, termasuk Dispensasi FTM.
     */
    public function test_transportir_application_page_renders_all_finding_statuses(): void
    {
        $finding = $this->makeFinding();

        foreach (FindingStatus::cases() as $status) {
            $finding->update([
                'status' => $status,
                'dispensation_due_date' => $status === FindingStatus::Dispensation
                    ? Carbon::today()->addDays(5)->toDateString()
                    : null,
            ]);

            Filament::setCurrentPanel('transportir');
            $this->actingAs($this->owner);

            Livewire::test(TransportirViewKimApplication::class, ['record' => $this->application->getRouteKey()])
                ->assertSuccessful()
                ->assertSee($status->label());
        }
    }
}
