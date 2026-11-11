<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Transportir\Widgets\VehicleProgressStepper;
use App\Models\DocumentType;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Application\VehicleProgressService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleProgressStepperTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $transportir;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);

        $this->transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($this->transporter);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
    }

    private function makeApplication(ApplicationStatus $status = ApplicationStatus::Draft): KimApplication
    {
        return KimApplication::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'transporter_id' => $this->transporter->id,
            'status' => $status,
        ]);
    }

    private function verifyAllRequiredDocuments(): void
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            VehicleDocument::factory()
                ->for($this->vehicle)
                ->for(DocumentType::where('code', $code)->firstOrFail())
                ->create(['verification_status' => DocumentVerificationStatus::Verified]);
        }
    }

    private function steps(KimApplication $application): array
    {
        return app(VehicleProgressService::class)->stepsFor($application->fresh());
    }

    private function step(array $steps, string $key): array
    {
        return collect($steps)->firstWhere('key', $key);
    }

    // ---------- Service: tahap dokumen ----------

    public function test_document_step_is_pending_without_uploads(): void
    {
        $application = $this->makeApplication();

        $this->assertSame('pending', $this->step($this->steps($application), 'documents')['state']);
    }

    public function test_document_step_is_active_when_waiting_for_verification(): void
    {
        $application = $this->makeApplication();

        // Semua dokumen wajib sudah diunggah, sebagian masih pending.
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            VehicleDocument::factory()
                ->for($this->vehicle)
                ->for(DocumentType::where('code', $code)->firstOrFail())
                ->create(['verification_status' => DocumentVerificationStatus::Pending]);
        }

        $step = $this->step($this->steps($application), 'documents');

        $this->assertSame('active', $step['state']);
        $this->assertStringContainsString('Menunggu verifikasi', $step['description']);
    }

    public function test_document_step_is_done_when_all_required_documents_verified(): void
    {
        $application = $this->makeApplication();
        $this->verifyAllRequiredDocuments();

        $this->assertSame('done', $this->step($this->steps($application), 'documents')['state']);
    }

    // ---------- Service: tahap pemeriksaan (checklist + temuan) ----------

    public function test_inspection_step_is_pending_for_draft_application(): void
    {
        $application = $this->makeApplication();

        $this->assertSame('pending', $this->step($this->steps($application), 'inspection')['state']);
    }

    public function test_inspection_step_is_active_while_inspection_running(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);
        // Pemeriksaan berjalan setelah jadwal ditetapkan dan tanggalnya tiba.
        $application->update(['inspection_scheduled_at' => now()->subHour()]);

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('active', $step['state']);
        $this->assertStringContainsString('0 dari 3', $step['description']);
    }

    public function test_inspection_step_is_pending_when_application_submitted_without_schedule(): void
    {
        // Bug: dulu status Submitted langsung membuat tahap pemeriksaan aktif.
        $application = $this->makeApplication(ApplicationStatus::Submitted);

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('pending', $step['state']);
        $this->assertStringContainsString('Menunggu penjadwalan', $step['description']);
    }

    public function test_inspection_step_is_pending_when_schedule_is_in_the_future(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Submitted);
        $application->update(['inspection_scheduled_at' => now()->addDays(2)]);

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('pending', $step['state']);
        $this->assertStringContainsString('dijadwalkan pada', $step['description']);
    }

    public function test_inspection_step_is_active_once_schedule_is_due(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Submitted);
        $application->update(['inspection_scheduled_at' => now()->subMinute()]);

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('active', $step['state']);
    }

    public function test_inspection_step_is_done_when_all_departments_complete(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);
        $application->update(['inspection_scheduled_at' => now()->subDay()]);

        $inspection = app(InspectionService::class);
        $officers = [];

        foreach (Department::inspectingDepartments() as $department) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($department->value);
            $officers[$department->value] = $officer;
        }

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas(
                'checklist',
                fn ($query) => $query->where('department', $department->value)
            )->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $officers[$department->value],
                );
            }
        }

        $this->assertSame('done', $this->step($this->steps($application), 'inspection')['state']);
    }

    public function test_inspection_step_is_done_without_findings_after_all_checklists(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);
        $application->update(['inspection_scheduled_at' => now()->subDay()]);

        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($department->value);

            $items = InspectionItem::whereHas(
                'checklist',
                fn ($query) => $query->where('department', $department->value)
            )->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $officer,
                );
            }
        }

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('done', $step['state']);
        $this->assertSame('Seluruh checklist selesai, tanpa temuan', $step['description']);
    }

    public function test_inspection_step_warns_when_open_findings_exist(): void
    {
        $application = $this->makeApplication(ApplicationStatus::RevisionRequired);

        Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->transportir->id,
        ]);

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('warning', $step['state']);
        $this->assertStringContainsString('1 temuan', $step['description']);
    }

    public function test_inspection_step_warning_includes_checklist_progress(): void
    {
        $application = $this->makeApplication(ApplicationStatus::RevisionRequired);

        Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->transportir->id,
        ]);

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertStringContainsString('checklist 0/3 fungsi', $step['description']);
    }

    public function test_inspection_step_is_done_when_all_findings_verified_and_checklists_complete(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);
        $application->update(['inspection_scheduled_at' => now()->subDay()]);

        Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'Sudah diperbaiki',
            'status' => FindingStatus::Verified,
            'created_by' => $this->transportir->id,
            'closed_at' => now(),
        ]);

        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($department->value);

            $items = InspectionItem::whereHas(
                'checklist',
                fn ($query) => $query->where('department', $department->value)
            )->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $officer,
                );
            }
        }

        $step = $this->step($this->steps($application), 'inspection');

        $this->assertSame('done', $step['state']);
        $this->assertSame('Seluruh checklist selesai dan temuan tuntas', $step['description']);
    }

    public function test_inspection_step_fails_when_application_rejected(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Rejected);

        $this->assertSame('failed', $this->step($this->steps($application), 'inspection')['state']);
    }

    // ---------- Service: daftar langkah ----------

    public function test_steps_do_not_include_kim_issued_step(): void
    {
        $steps = $this->steps($this->makeApplication(ApplicationStatus::Approved));

        $this->assertNotContains('kim', array_column($steps, 'key'));
    }

    public function test_steps_contain_every_approval_breaker(): void
    {
        $steps = $this->steps($this->makeApplication());

        $this->assertCount(8, $steps);
        $this->assertSame(
            ['vehicle', 'documents', 'inspection', 'hsse', 'distribusi', 'qq', 'rsd', 'ftm'],
            array_column($steps, 'key')
        );
    }

    // ---------- Service: tahap approval ----------

    private function approveApplication(KimApplication $application, array $departments, ApprovalDecision $decision = ApprovalDecision::Approved): void
    {
        foreach ($departments as $department) {
            $application->approvals()->create([
                'department' => $department->value,
                'decision' => $decision,
                'approved_by' => $this->transportir->id,
                'decided_at' => now(),
            ]);
        }
    }

    public function test_supervisor_steps_stay_pending_during_document_verification(): void
    {
        // Bug: saat tahap verifikasi dokumen, langkah Pengawas HSSE sudah menyala
        // padahal pemeriksaan belum berjalan. Reproduksi alurnya secara nyata.
        $application = $this->makeApplication(ApplicationStatus::Submitted);

        $this->vehicle->documents()->update([
            'verification_status' => DocumentVerificationStatus::Pending,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $steps = $this->steps($application->fresh());

        foreach (Department::inspectingDepartments() as $department) {
            $this->assertSame(
                'pending',
                $this->step($steps, $department->value)['state'],
                'Langkah pengawas tidak boleh berjalan sebelum pemeriksaan dimulai.',
            );
        }

        $this->assertSame('pending', $this->step($steps, 'ftm')['state']);
    }

    public function test_department_steps_are_pending_before_inspection_finished(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);

        $steps = $this->steps($application);

        // Pemeriksaan belum tuntas: seluruh langkah pengawas masih menunggu.
        $this->assertSame('pending', $this->step($steps, 'hsse')['state']);
        $this->assertSame('pending', $this->step($steps, 'distribusi')['state']);
        $this->assertSame('pending', $this->step($steps, 'qq')['state']);
        $this->assertSame('pending', $this->step($steps, 'rsd')['state']);
        $this->assertSame('pending', $this->step($steps, 'ftm')['state']);
    }

    public function test_only_approved_department_is_done(): void
    {
        $application = $this->makeApplication(ApplicationStatus::PendingApproval);

        $this->approveApplication($application, [Department::Hsse]);

        $steps = $this->steps($application);

        $this->assertSame('done', $this->step($steps, 'hsse')['state']);
        // Pemeriksaan belum tuntas, jadi fungsi berikutnya belum berjalan.
        $this->assertSame('pending', $this->step($steps, 'distribusi')['state']);
        $this->assertSame('pending', $this->step($steps, 'qq')['state']);
        $this->assertSame('pending', $this->step($steps, 'rsd')['state']);
        $this->assertSame('pending', $this->step($steps, 'ftm')['state']);
    }

    public function test_ftm_step_becomes_active_after_rsd_approves(): void
    {
        $application = $this->makeApplication(ApplicationStatus::PendingApproval);

        $this->approveApplication($application, [
            ...Department::inspectingDepartments(),
            Department::Rsd,
        ]);

        $steps = $this->steps($application);

        foreach (Department::inspectingDepartments() as $department) {
            $this->assertSame('done', $this->step($steps, $department->value)['state']);
        }

        $rsd = $this->step($steps, 'rsd');
        $this->assertSame('done', $rsd['state']);

        $ftm = $this->step($steps, 'ftm');
        $this->assertSame('active', $ftm['state']);
        $this->assertSame('Menunggu persetujuan FTM', $ftm['description']);
    }

    public function test_rsd_step_becomes_active_after_all_functions_approve(): void
    {
        $application = $this->makeApplication(ApplicationStatus::PendingApproval);

        $this->approveApplication($application, Department::inspectingDepartments());

        $steps = $this->steps($application);

        foreach (Department::inspectingDepartments() as $department) {
            $this->assertSame('done', $this->step($steps, $department->value)['state']);
        }

        $rsd = $this->step($steps, 'rsd');
        $this->assertSame('active', $rsd['state']);
        $this->assertSame('Menunggu persetujuan RSD', $rsd['description']);

        $ftm = $this->step($steps, 'ftm');
        $this->assertSame('pending', $ftm['state']);
        $this->assertSame('Menunggu persetujuan RSD', $ftm['description']);
    }

    public function test_ftm_step_is_done_when_ftm_approved(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Approved);

        $this->approveApplication($application, [
            ...Department::approvableDepartments(),
            Department::Ftm,
        ]);

        $ftm = $this->step($this->steps($application), 'ftm');

        $this->assertSame('done', $ftm['state']);
    }

    public function test_rejecting_department_marks_its_own_step_failed(): void
    {
        $application = $this->makeApplication(ApplicationStatus::PendingApproval);

        $this->approveApplication($application, [Department::Hsse]);
        $this->approveApplication($application, [Department::Distribusi], ApprovalDecision::Rejected);

        $steps = $this->steps($application);

        $this->assertSame('failed', $this->step($steps, 'distribusi')['state']);
        $this->assertSame('done', $this->step($steps, 'hsse')['state']);
    }

    public function test_rejected_application_marks_ftm_step_failed(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Rejected);

        $ftm = $this->step($this->steps($application), 'ftm');

        $this->assertSame('failed', $ftm['state']);
        $this->assertSame('Pengajuan ditolak', $ftm['description']);
    }

    public function test_approval_steps_stay_pending_until_inspection_finishes(): void
    {
        $application = $this->makeApplication(ApplicationStatus::PendingApproval);

        $this->approveApplication($application, [Department::Hsse]);

        $steps = $this->steps($application);

        // Bug: dulu langkah Pengawas langsung menyala saat verifikasi dokumen.
        foreach (['hsse', 'distribusi', 'qq', 'rsd', 'ftm'] as $key) {
            $this->assertContains($this->step($steps, $key)['state'], ['done', 'pending'], $key.' seharusnya belum berjalan.');
        }
    }

    public function test_first_pending_department_becomes_current_when_inspection_ready(): void
    {
        $application = $this->applicationWithCompletedInspection(ApplicationStatus::Inspection);
        $application->update(['status' => ApplicationStatus::PendingApproval]);

        $this->approveApplication($application, [Department::Hsse]);

        $steps = $this->steps($application);

        $this->assertSame(
            array_search('distribusi', array_column($steps, 'key'), true),
            app(VehicleProgressService::class)->currentStepIndex($steps)
        );
    }

    /**
     * Pengajuan dengan seluruh checklist terisi dan tanpa temuan terbuka.
     */
    private function applicationWithCompletedInspection(ApplicationStatus $status): KimApplication
    {
        $application = $this->makeApplication($status);
        $application->update(['inspection_scheduled_at' => now()->subDay()]);

        $service = app(InspectionService::class);
        $inspector = $this->makeSuperAdmin();

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas(
                'checklist',
                fn ($q) => $q->where('department', $department->value)
            )->where('is_active', true)->get();

            foreach ($items as $item) {
                $service->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $inspector,
                );
            }
        }

        return $application->fresh();
    }

    // ---------- Widget ----------

    public function test_widget_is_visible_only_for_transportir(): void
    {
        $this->actingAs($this->transportir);
        $this->assertTrue(VehicleProgressStepper::canView());

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');
        auth()->logout();
        $this->actingAs($admin);
        $this->assertFalse(VehicleProgressStepper::canView());
    }

    public function test_widget_renders_selected_application_steps(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);

        $this->actingAs($this->transportir);

        Livewire::test(VehicleProgressStepper::class)
            ->assertSet('applicationId', $application->id)
            ->assertSee('Progres Pengajuan KIM')
            ->assertSee('Pendaftaran Kendaraan')
            ->assertSee('Verifikasi Dokumen')
            ->assertSee('Pemeriksaan & Temuan')
            ->assertSee('Pengawas HSSE')
            ->assertSee('Pengawas Distribusi')
            ->assertSee('Pengawas QQ')
            ->assertSee('Pengawas RSD')
            ->assertSee('FTM')
            ->assertSee($this->vehicle->registration_number);
    }

    public function test_dropdown_excludes_vehicles_without_application(): void
    {
        $application = $this->makeApplication();

        $vehicleWithoutApplication = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 9999 TANPA',
        ]);

        $this->actingAs($this->transportir);

        $options = Livewire::test(VehicleProgressStepper::class)->instance()->getApplicationOptions();

        $this->assertArrayHasKey($application->id, $options);
        $this->assertStringNotContainsString('G 9999 TANPA', implode(' ', $options));
        $this->assertNotNull($vehicleWithoutApplication);
    }

    public function test_dropdown_excludes_other_companies_applications(): void
    {
        $ownApplication = $this->makeApplication();

        $otherTransporter = Transporter::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'transporter_id' => $otherTransporter->id,
            'registration_number' => 'G 8888 LAIN',
        ]);
        $otherApplication = KimApplication::factory()->create([
            'vehicle_id' => $otherVehicle->id,
            'transporter_id' => $otherTransporter->id,
        ]);

        $this->actingAs($this->transportir);

        $options = Livewire::test(VehicleProgressStepper::class)->instance()->getApplicationOptions();

        $this->assertArrayHasKey($ownApplication->id, $options);
        $this->assertArrayNotHasKey($otherApplication->id, $options);
    }

    public function test_widget_shows_empty_state_without_applications(): void
    {
        $this->actingAs($this->transportir);

        Livewire::test(VehicleProgressStepper::class)
            ->assertSee('Belum ada pengajuan KIM')
            ->assertSee('Buat Pengajuan KIM');
    }

    public function test_widget_switches_application_via_dropdown(): void
    {
        $first = $this->makeApplication();

        $secondVehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        $second = KimApplication::factory()->create([
            'vehicle_id' => $secondVehicle->id,
            'transporter_id' => $this->transporter->id,
            'status' => ApplicationStatus::Approved,
        ]);

        $this->actingAs($this->transportir);

        Livewire::test(VehicleProgressStepper::class)
            // Pengajuan yang masih berjalan diprioritaskan sebagai pilihan awal.
            ->assertSet('applicationId', $first->id)
            ->set('applicationId', $second->id)
            ->assertSet('applicationId', $second->id)
            ->assertSee($second->application_number);
    }
}
