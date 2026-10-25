<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\FindingActionSubmitted;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * BUG-01: notifikasi tindak lanjut temuan harus benar-benar terkirim.
 * BUG-02: temuan harus dibersihkan saat hasil inspeksi diubah menjadi bukan temuan.
 */
class FindingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $hsse;

    private User $pengawas;

    private Vehicle $vehicle;

    private KimApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        $this->pengawas = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->pengawas->assignRole('pengawas_hsse');

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        $this->application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subHour(),
            'inspection_scheduled_by' => $this->hsse->id,
        ]);
    }

    private function hsseItem(): InspectionItem
    {
        return InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function makeFinding(): Finding
    {
        return Finding::create([
            'kim_application_id' => $this->application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->hsse->id,
        ]);
    }

    // ---------- BUG-01 ----------

    public function test_submitting_action_notifies_verifier(): void
    {
        Notification::fake();

        $finding = $this->makeFinding();

        $action = app(FindingService::class)->submitAction(
            $finding,
            $this->owner,
            'APAR sudah diganti',
        );

        $this->assertInstanceOf(FindingAction::class, $action);
        $this->assertSame(FindingStatus::SubmittedForVerification, $finding->fresh()->status);

        Notification::assertSentTo($this->hsse, FindingActionSubmitted::class);
    }

    public function test_submitting_action_returns_created_action(): void
    {
        Notification::fake();

        $finding = $this->makeFinding();

        $action = app(FindingService::class)->submitAction($finding, $this->owner, 'Sudah diperbaiki');

        $this->assertNotNull($action->id);
        $this->assertSame($finding->id, $action->finding_id);
        $this->assertSame('Sudah diperbaiki', $action->description);
    }

    // ---------- BUG-02 ----------

    public function test_changing_result_from_finding_to_compliant_removes_orphan_finding(): void
    {
        $item = $this->hsseItem();
        $inspection = app(InspectionService::class);

        $inspection->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        $this->assertSame(1, $this->application->findings()->count());

        $inspection->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
        );

        $this->assertSame(0, $this->application->fresh()->findings()->count());
    }

    public function test_changing_result_keeps_finding_that_already_has_action(): void
    {
        $item = $this->hsseItem();
        $inspection = app(InspectionService::class);

        $inspection->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        $finding = $this->application->fresh()->findings()->firstOrFail();

        app(FindingService::class)->submitAction($finding, $this->owner, 'APAR sudah diganti');

        $inspection->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
        );

        // Temuan tetap ada sebagai jejak audit karena sudah ada tindak lanjut.
        $this->assertSame(1, $this->application->fresh()->findings()->count());
        $this->assertSame(FindingStatus::SubmittedForVerification, $finding->fresh()->status);
    }

    public function test_changing_result_to_not_applicable_removes_orphan_finding(): void
    {
        $item = $this->hsseItem();
        $inspection = app(InspectionService::class);

        $inspection->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        $inspection->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::NotApplicable,
            $this->hsse,
        );

        $this->assertSame(0, $this->application->fresh()->findings()->count());
    }
}
