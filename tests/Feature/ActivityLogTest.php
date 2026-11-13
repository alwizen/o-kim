<?php

namespace Tests\Feature;

use App\Actions\User\DeactivateUser;
use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Audit\ActivityLogger;
use App\Services\Document\VehicleDocumentService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $transportir;

    private Vehicle $vehicle;

    private User $hsseOfficer;

    private User $hssePengawas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Filament::setCurrentPanel('admin');
        Storage::fake(config('media.disk'));

        $transporter = Transporter::factory()->create();

        $this->transportir = User::factory()->create([
            'account_status' => AccountStatus::Approved,
            'email' => 'transportir@okim.test',
            'password' => 'password',
        ]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($transporter);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        $this->hsseOfficer = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsseOfficer->assignRole('hsse');

        $this->hssePengawas = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hssePengawas->assignRole('pengawas_hsse');
    }

    private function scheduledApplication(): KimApplication
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }

        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        app(KimApplicationService::class)->submit($application, $this->transportir);

        $application->fresh()->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->hsseOfficer->id,
        ]);

        return $application->fresh();
    }

    /**
     * Isi seluruh butir checklist ketiga fungsi sebagai prasyarat approval.
     */
    private function completeAllChecklists(KimApplication $application): void
    {
        $officers = [
            Department::Hsse->value => $this->hsseOfficer,
            Department::Distribusi->value => tap(
                User::factory()->create(['account_status' => AccountStatus::Approved]),
                fn (User $user) => $user->assignRole('distribusi'),
            ),
            Department::Qq->value => tap(
                User::factory()->create(['account_status' => AccountStatus::Approved]),
                fn (User $user) => $user->assignRole('qq'),
            ),
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
    }

    public function test_successful_login_is_logged(): void
    {
        $this->post('/masuk', [
            'email' => $this->transportir->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'login',
            'user_id' => $this->transportir->id,
            'user_name' => $this->transportir->name,
        ]);
    }

    public function test_failed_login_is_logged_without_user(): void
    {
        $this->post('/masuk', [
            'email' => $this->transportir->email,
            'password' => 'salah-total',
        ]);

        $log = ActivityLog::where('event', 'login_failed')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertSame('Tamu', $log->user_name);
        $this->assertSame($this->transportir->email, $log->properties['email']);
    }

    public function test_logout_is_logged_once(): void
    {
        $this->actingAs($this->transportir)
            ->post('/logout');

        $this->assertSame(1, ActivityLog::where('event', 'logout')->count());
    }

    public function test_filament_logout_is_logged_once(): void
    {
        // Logout panel admin memicu event Logout yang juga dipakai login web;
        // pastikan hanya satu baris tercatat.
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $this->post('/admin/logout');

        $this->assertSame(1, ActivityLog::where('event', 'logout')->count());
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'logout',
            'user_id' => $admin->id,
            'status' => ActivityLog::STATUS_SUCCESS,
        ]);
    }

    public function test_inspection_is_logged_once_per_department_not_per_item(): void
    {
        $application = $this->scheduledApplication();

        $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->get();

        $this->assertGreaterThan(1, $items->count());

        $this->actingAs($this->hsseOfficer);

        foreach ($items as $item) {
            app(InspectionService::class)->recordResult(
                $application->fresh(),
                $item,
                InspectionResultValue::Compliant,
                $this->hsseOfficer,
            );
        }

        // Satu log per fungsi, bukan satu log per butir checklist.
        $this->assertSame(1, ActivityLog::where('event', 'inspection.completed')->count());

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'inspection.completed',
            'user_id' => $this->hsseOfficer->id,
            'subject_label' => $application->application_number,
        ]);
    }

    public function test_inspection_completion_is_not_logged_twice_when_reassessed(): void
    {
        $application = $this->scheduledApplication();

        $this->actingAs($this->hsseOfficer);

        $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->get();

        foreach ($items as $item) {
            app(InspectionService::class)->recordResult(
                $application->fresh(),
                $item,
                InspectionResultValue::Compliant,
                $this->hsseOfficer,
            );
        }

        // Penilaian ulang (mis. setelah revisi) tidak boleh menambah log baru.
        foreach ($items as $item) {
            app(InspectionService::class)->recordResult(
                $application->fresh(),
                $item,
                InspectionResultValue::Compliant,
                $this->hsseOfficer,
            );
        }

        $this->assertSame(1, ActivityLog::where('event', 'inspection.completed')->count());
    }

    public function test_approval_is_logged(): void
    {
        $application = $this->scheduledApplication();

        $this->completeAllChecklists($application);

        $this->actingAs($this->hssePengawas);

        app(ApprovalService::class)->decide(
            $application->fresh(),
            Department::Hsse,
            ApprovalDecision::Approved,
            $this->hssePengawas,
        );

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'approval.approved',
            'user_id' => $this->hssePengawas->id,
        ]);
    }

    public function test_document_upload_and_delete_are_logged(): void
    {
        $this->actingAs($this->transportir);

        $document = app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('stnk.pdf', 100, 'application/pdf'),
            ['document_number' => 'X-1', 'expires_at' => '2027-01-01'],
        );

        $this->assertDatabaseHas('activity_logs', ['event' => 'document.uploaded']);

        app(VehicleDocumentService::class)->delete($document);

        $this->assertDatabaseHas('activity_logs', ['event' => 'document.deleted']);
    }

    public function test_kim_issuance_is_logged(): void
    {
        $approvals = app(ApprovalService::class);

        $application = $this->scheduledApplication();

        $officers = [
            Department::Hsse->value => $this->hsseOfficer,
        ];

        // Lengkapi seluruh butir ketiga fungsi agar pengajuan siap approval.
        $distribution = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $distribution->assignRole('distribusi');
        $qq = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $qq->assignRole('qq');

        $officers[Department::Distribusi->value] = $distribution;
        $officers[Department::Qq->value] = $qq;

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

        $approvals->decide($application->fresh(), Department::Hsse, ApprovalDecision::Approved, $this->hssePengawas);

        $distPengawas = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $distPengawas->assignRole('pengawas_distribusi');
        $approvals->decide($application->fresh(), Department::Distribusi, ApprovalDecision::Approved, $distPengawas);

        $qqPengawas = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $qqPengawas->assignRole('pengawas_qq');
        $approvals->decide($application->fresh(), Department::Qq, ApprovalDecision::Approved, $qqPengawas);

        $rsd = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $rsd->givePermissionTo(['access_admin_panel', 'view_applications', 'approve_rsd']);
        $approvals->decide($application->fresh(), Department::Rsd, ApprovalDecision::Approved, $rsd);

        $ftm = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $ftm->assignRole('ftm');
        $approvals->decide($application->fresh(), Department::Ftm, ApprovalDecision::Approved, $ftm);

        $this->actingAs($ftm);

        app(KimIssuanceService::class)->issue($application->fresh());

        $this->assertDatabaseHas('activity_logs', ['event' => 'kim.issued']);
    }

    public function test_failed_login_is_marked_as_failed_status(): void
    {
        $this->post('/masuk', [
            'email' => $this->transportir->email,
            'password' => 'salah-total',
        ]);

        $log = ActivityLog::where('event', 'login_failed')->first();

        $this->assertNotNull($log);
        $this->assertSame(ActivityLog::STATUS_FAILED, $log->status);
    }

    public function test_failed_approval_is_logged_with_reason_and_status(): void
    {
        // Checklist belum lengkap: approval HSSE lewat panel harus gagal dan tercatat.
        $application = $this->scheduledApplication();

        $this->actingAs($this->hssePengawas);

        Livewire::test(
            ViewKimApplication::class,
            ['record' => $application->getRouteKey()],
        )
            ->assertActionVisible('approve_hsse')
            ->mountAction('approve_hsse')
            ->callMountedAction();

        $log = ActivityLog::where('event', 'approval')
            ->where('status', ActivityLog::STATUS_FAILED)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->hssePengawas->id, $log->user_id);
        $this->assertSame($application->application_number, $log->subject_label);
        $this->assertStringContainsString('belum lengkap', $log->description);
        $this->assertNotNull($log->properties['error'] ?? null);
    }

    public function test_approval_failure_reason_mentions_incomplete_departments(): void
    {
        $application = $this->scheduledApplication();

        $blockers = app(InspectionService::class)->approvalBlockers($application);

        $this->assertNotEmpty($blockers);
        $this->assertStringContainsString('HSSE', implode(' ', $blockers));
        $this->assertStringContainsString('Distribusi', implode(' ', $blockers));
        $this->assertStringContainsString('QQ', implode(' ', $blockers));
    }

    public function test_successful_approval_is_logged_with_success_status(): void
    {
        $application = $this->scheduledApplication();
        $this->completeAllChecklists($application);

        $this->actingAs($this->hssePengawas);

        app(ApprovalService::class)->decide(
            $application->fresh(),
            Department::Hsse,
            ApprovalDecision::Approved,
            $this->hssePengawas,
        );

        $log = ActivityLog::where('event', 'approval.approved')->firstOrFail();

        $this->assertSame(ActivityLog::STATUS_SUCCESS, $log->status);
    }

    public function test_user_action_failure_is_logged(): void
    {
        // Menonaktifkan super admin aktif terakhir ditolak DeactivateUser.
        // Tombol memang disembunyikan untuk diri sendiri, jadi kegagalan dipicu
        // lewat service untuk memastikan jalur catch mencatat log.
        $superAdmin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        try {
            app(DeactivateUser::class)->execute($superAdmin, $superAdmin);
            $this->fail('DeactivateUser seharusnya menolak menonaktifkan akun sendiri.');
        } catch (ValidationException $e) {
            app(ActivityLogger::class)->failureFromValidation(
                'user',
                $superAdmin,
                $e,
                ['action' => 'deactivate'],
            );
        }

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'user',
            'status' => ActivityLog::STATUS_FAILED,
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_activity_log_resource_requires_permission(): void
    {
        $superAdmin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);
        $this->assertTrue(ActivityLogResource::canAccess());

        $other = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $other->assignRole('hsse');

        $this->actingAs($other);
        $this->assertFalse(ActivityLogResource::canAccess());
    }

    public function test_role_label_shows_transportir_for_transporter_role(): void
    {
        $this->assertSame('Transportir', ActivityLogsTable::roleLabel('transportir'));
        $this->assertSame('HSSE (TAD)', ActivityLogsTable::roleLabel('hsse'));
        $this->assertSame('—', ActivityLogsTable::roleLabel(null));
    }

    public function test_log_keeps_user_name_after_user_deleted(): void
    {
        $this->actingAs($this->transportir)->post('/logout');

        $log = ActivityLog::where('event', 'logout')->firstOrFail();

        $this->transportir->delete();

        $log->refresh();

        $this->assertNull($log->user_id);
        $this->assertSame($this->transportir->name, $log->user_name);
    }

    public function test_verified_document_cannot_be_deleted(): void
    {
        $document = $this->uploadVehicleDocument($this->vehicle, 'STNK', verify: true);

        $this->assertSame(DocumentVerificationStatus::Verified, $document->verification_status);

        $this->expectException(ValidationException::class);

        app(VehicleDocumentService::class)->delete($document);
    }
}
