<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Enums\KimCardStatus;
use App\Models\DocumentType;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Document\VehicleDocumentService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use App\Services\Kim\KimPdfService;
use App\Services\Kim\KimValidityService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovalAndIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private KimApplication $application;

    private array $officers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $transporter = Transporter::factory()->create();
        $owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $owner->assignRole('transportir');
        $owner->transporters()->attach($transporter);
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role);
            $this->officers[$role] = $officer;
        }

        $docService = app(VehicleDocumentService::class);
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $docService->upload(
                $this->vehicle,
                DocumentType::where('code', $code)->value('id'),
                UploadedFile::fake()->create("{$code}.pdf", 100, 'application/pdf'),
                ['expires_at' => now()->addMonths(4)->toDateString()]
            );
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $owner);

        // Prasyarat pemeriksaan: jadwal pelaksanaan wajib ditetapkan.
        $this->application->update([
            'inspection_scheduled_at' => now()->addDay(),
            'inspection_scheduled_by' => $this->officers['ftm']->id,
        ]);
    }

    private function completeInspections(): void
    {
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
    }

    private function approveAllFunctional(): void
    {
        $approval = app(ApprovalService::class);
        foreach (Department::inspectingDepartments() as $department) {
            $approval->decide(
                $this->application->fresh(),
                $department,
                ApprovalDecision::Approved,
                $this->officers[$department->value],
            );
        }
    }

    public function test_ftm_cannot_approve_before_functional_approvals(): void
    {
        $this->completeInspections();

        $this->expectException(ValidationException::class);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->officers['ftm'],
        );
    }

    public function test_approval_requires_correct_permission(): void
    {
        $this->completeInspections();

        $this->expectException(ValidationException::class);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Hsse,
            ApprovalDecision::Approved,
            $this->officers['distribusi'],
        );
    }

    public function test_duplicate_approval_same_department_is_rejected(): void
    {
        $this->completeInspections();

        $approval = app(ApprovalService::class);
        $approval->decide($this->application->fresh(), Department::Hsse, ApprovalDecision::Approved, $this->officers['hsse']);

        $this->expectException(ValidationException::class);
        $approval->decide($this->application->fresh(), Department::Hsse, ApprovalDecision::Approved, $this->officers['hsse']);
    }

    public function test_application_reaches_pending_approval_after_all_three(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();

        $this->assertSame(ApplicationStatus::PendingApproval, $this->application->fresh()->status);
    }

    public function test_rejection_requires_reason(): void
    {
        $this->completeInspections();

        $this->expectException(ValidationException::class);

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Hsse,
            ApprovalDecision::Rejected,
            $this->officers['hsse'],
        );
    }

    public function test_rejection_sets_application_rejected(): void
    {
        $this->completeInspections();

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Hsse,
            ApprovalDecision::Rejected,
            $this->officers['hsse'],
            'Tidak memenuhi syarat keselamatan'
        );

        $this->assertSame(ApplicationStatus::Rejected, $this->application->fresh()->status);
        $this->assertStringContainsString('Tidak memenuhi', $this->application->fresh()->rejection_reason);
    }

    public function test_full_approval_chain_leads_to_approved(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();

        app(ApprovalService::class)->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->officers['ftm'],
        );

        $this->assertSame(ApplicationStatus::Approved, $this->application->fresh()->status);
        $this->assertNotNull($this->application->fresh()->final_approved_at);
    }

    public function test_kim_cannot_be_issued_without_final_approval(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();

        $this->expectException(ValidationException::class);

        app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    public function test_kim_issued_with_unique_number_and_qr_token(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->assertNotNull($card->kim_number);
        $this->assertStringStartsWith('KIM/FT-TGL/', $card->kim_number);
        $this->assertEquals(48, strlen($card->qr_token));
        $this->assertSame(ApplicationStatus::Issued, $this->application->fresh()->status);
    }

    public function test_kim_cannot_be_issued_twice(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);

        $issuance = app(KimIssuanceService::class);
        $issuance->issue($this->application->fresh());

        $this->expectException(ValidationException::class);
        $issuance->issue($this->application->fresh());
    }

    public function test_issued_kim_pdf_can_be_downloaded_without_invalid_filename(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->get(route('kim.download', $card));

        $response->assertSuccessful();

        $disposition = $response->headers->get('content-disposition');

        $this->assertNotNull($disposition);
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringNotContainsString('\\', $disposition);
    }

    public function test_generated_pdf_path_is_safe(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $path = app(KimPdfService::class)->generate($card);

        $this->assertStringStartsWith('kim-cards/', $path);
        $this->assertSame(1, substr_count($path, '/'), 'Path hanya boleh punya satu pemisah direktori.');
        $this->assertStringEndsWith('.pdf', $path);
    }

    public function test_validity_follows_earliest_document_expiry(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);

        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        // Dokumen kedaluwarsa 4 bulan lagi, lebih awal dari batas pengaturan 6 bulan → dipakai 4 bulan.
        $expected = now()->addMonths(4);
        $this->assertEquals($expected->toDateString(), $card->expires_at->toDateString());
    }

    public function test_validity_service_uses_settings_cap(): void
    {
        $docService = app(VehicleDocumentService::class);
        $this->vehicle->documents()->delete();

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $docService->upload(
                $this->vehicle,
                DocumentType::where('code', $code)->value('id'),
                UploadedFile::fake()->create("{$code}.pdf", 100, 'application/pdf'),
                ['expires_at' => now()->addYears(5)->toDateString()]
            );
        }

        $expiresAt = app(KimValidityService::class)->calculateExpiresAt(
            $this->application,
            now()
        );

        $this->assertEquals(
            now()->addMonths(KimValidityService::DEFAULT_VALIDITY_MONTHS)->toDateString(),
            $expiresAt->toDateString()
        );
    }

    public function test_public_validation_shows_correct_status(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);
        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $this->get(route('kim.validate', $card->qr_token))
            ->assertSuccessful()
            ->assertSee($card->kim_number)
            ->assertSee('Aktif');
    }

    public function test_public_validation_rejects_invalid_token(): void
    {
        $this->get(route('kim.validate', 'token-tidak-valid'))
            ->assertSuccessful()
            ->assertSee('tidak ditemukan');
    }

    public function test_public_validation_shows_revoked_card_as_revoked(): void
    {
        $this->completeInspections();
        $this->approveAllFunctional();
        app(ApprovalService::class)->decide($this->application->fresh(), Department::Ftm, ApprovalDecision::Approved, $this->officers['ftm']);
        $card = app(KimIssuanceService::class)->issue($this->application->fresh());

        $card->update(['status' => KimCardStatus::Revoked]);

        $this->get(route('kim.validate', $card->qr_token))
            ->assertSee('Dicabut');
    }
}
