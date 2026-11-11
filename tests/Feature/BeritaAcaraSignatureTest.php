<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Models\BeritaAcaraDocument;
use App\Models\DocumentScanLog;
use App\Models\DocumentSignature;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Finding\BeritaAcaraService;
use App\Services\Finding\DispensationService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionService;
use App\Services\Setting\SettingsService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeritaAcaraSignatureTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private Vehicle $vehicle;

    private KimApplication $application;

    /** @var array<string, User> */
    private array $officers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');
        Storage::fake(config('media.disk'));

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT BA Utama']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        foreach (['hsse', 'distribusi', 'qq', 'ftm', 'rsd'] as $role) {
            $officer = User::factory()->create([
                'account_status' => AccountStatus::Approved,
                'jabatan' => 'Jabatan '.strtoupper($role),
            ]);

            if ($role === 'rsd') {
                $officer->givePermissionTo('approve_rsd');
            } else {
                $officer->assignRole($role === 'ftm' ? ['ftm'] : [$role, 'pengawas_'.$role]);
            }

            $this->officers[$role] = $officer;
        }

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 5555 BA',
        ]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);
        $this->application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subDay(),
        ]);
    }

    /**
     * Isi seluruh checklist semua fungsi, dengan satu temuan di HSSE.
     */
    private function completeInspections(bool $createFinding = true): ?Finding
    {
        $inspection = app(InspectionService::class);
        $finding = null;

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas(
                'checklist',
                fn ($q) => $q->where('department', $department->value)->where('is_active', true)
            )->where('is_active', true)->get();

            foreach ($items as $index => $item) {
                $isTarget = $createFinding
                    && $finding === null
                    && $department === Department::Hsse
                    && $index === 0;

                $inspection->recordResult(
                    $this->application->fresh(),
                    $item,
                    $isTarget ? InspectionResultValue::Finding : InspectionResultValue::Compliant,
                    $this->officers[$department->value],
                    findingDescription: $isTarget ? 'APAR kedaluwarsa' : null,
                );

                if ($isTarget) {
                    $finding = Finding::where('kim_application_id', $this->application->id)->latest('id')->first();
                }
            }
        }

        return $finding;
    }

    /**
     * Isi checklist lalu tutup temuan HSSE sampai terverifikasi.
     */
    private function createVerifiedFinding(): Finding
    {
        $finding = $this->completeInspections();

        $action = app(FindingService::class)->submitAction($finding, $this->owner, 'APAR sudah diganti');

        app(FindingService::class)->verifyAction($finding->fresh(), $action, $this->officers['hsse'], true);

        return $finding->fresh();
    }

    /**
     * Setujui seluruh approval (3 fungsi + RSD + FTM).
     */
    private function approveAll(): void
    {
        $approval = app(ApprovalService::class);

        foreach (Department::approvableDepartments() as $department) {
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

    public function test_preview_before_approvals_has_no_signature_block(): void
    {
        $this->createVerifiedFinding();

        $service = app(BeritaAcaraService::class);

        $this->assertTrue($service->isEligible($this->application->fresh()));
        $this->assertFalse($service->isReadyForSignatures($this->application->fresh()));
        $this->assertNull($service->finalizeIfReady($this->application->fresh()));
        $this->assertDatabaseCount('berita_acara_documents', 0);
    }

    public function test_final_document_is_frozen_with_five_signatures(): void
    {
        $this->createVerifiedFinding();
        $this->approveAll();

        $service = app(BeritaAcaraService::class);
        $document = $service->finalizeIfReady($this->application->fresh());

        $this->assertInstanceOf(BeritaAcaraDocument::class, $document);
        $this->assertSame($this->application->application_number, $document->number);
        $this->assertSame(64, strlen($document->pdf_hash));
        $this->assertSame(5, $document->signatures()->count());

        foreach (Department::approvalSteps() as $department) {
            $this->assertDatabaseHas('document_signatures', [
                'signable_type' => BeritaAcaraDocument::class,
                'signable_id' => $document->id,
                'department' => $department->value,
            ]);
        }
    }

    public function test_signature_snapshots_name_jabatan_ip_and_decided_at(): void
    {
        $this->createVerifiedFinding();
        $this->approveAll();

        $document = app(BeritaAcaraService::class)->finalizeIfReady($this->application->fresh());

        $ftmSignature = $document->signatures
            ->firstWhere(fn (DocumentSignature $signature) => $signature->department === Department::Ftm);

        $this->assertSame($this->officers['ftm']->name, $ftmSignature->name);
        $this->assertSame($this->officers['ftm']->jabatan, $ftmSignature->jabatan);
        $this->assertNotNull($ftmSignature->decided_at);
        $this->assertNotNull($ftmSignature->ip_address);
        $this->assertSame(48, strlen($ftmSignature->qr_token));
    }

    public function test_finalization_is_idempotent_and_hash_is_stable(): void
    {
        $this->createVerifiedFinding();
        $this->approveAll();

        $service = app(BeritaAcaraService::class);

        $first = $service->finalizeIfReady($this->application->fresh());
        $hash = $first->pdf_hash;

        $second = $service->finalizeIfReady($this->application->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($hash, $second->pdf_hash);
        $this->assertDatabaseCount('berita_acara_documents', 1);
    }

    public function test_old_application_without_rsd_still_finalizes_with_placeholder(): void
    {
        $this->createVerifiedFinding();

        // Simulasikan data lama: approval dicatat langsung tanpa RSD (sebelum role ada).
        foreach ([...Department::inspectingDepartments(), Department::Ftm] as $department) {
            $this->application->fresh()->approvals()->create([
                'department' => $department->value,
                'decision' => ApprovalDecision::Approved->value,
                'approved_by' => $this->officers[$department->value]->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'legacy',
                'decided_at' => now(),
            ]);
        }

        $service = app(BeritaAcaraService::class);
        $document = $service->finalizeIfReady($this->application->fresh());

        $this->assertNotNull($document);
        $this->assertSame(4, $document->signatures()->count());

        $blocks = $service->signatureBlocks($document->fresh('signatures'));
        $rsdBlock = collect($blocks)->first(
            fn (array $block) => $block['department'] === Department::Rsd
        );

        $this->assertTrue($rsdBlock['placeholder']);
    }

    public function test_provisional_kim_is_not_eligible_for_berita_acara(): void
    {
        // Temuan HSSE sudah terverifikasi (memenuhi syarat dasar BA).
        $this->createVerifiedFinding();

        // Temuan kedua di Distribusi masih terbuka lalu didispensasi FTM.
        $distItem = InspectionItem::whereHas(
            'checklist',
            fn ($q) => $q->where('department', 'distribusi')->where('is_active', true)
        )->where('is_active', true)->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $distItem,
            InspectionResultValue::Finding,
            $this->officers['distribusi'],
            findingDescription: 'Selang bocor',
        );

        $second = Finding::where('kim_application_id', $this->application->id)
            ->where('department', Department::Distribusi->value)
            ->latest('id')
            ->firstOrFail();

        app(DispensationService::class)->grant(
            $second,
            $this->officers['ftm'],
            now()->addDays(3),
            'Sparepart di-inden',
        );

        $this->assertTrue($this->application->fresh()->hasActiveDispensation());
        $this->assertFalse(app(BeritaAcaraService::class)->isEligible($this->application->fresh()));
    }

    public function test_qr_toggle_hides_qr_but_keeps_names(): void
    {
        $this->createVerifiedFinding();
        $this->approveAll();

        $service = app(BeritaAcaraService::class);
        $document = $service->finalizeIfReady($this->application->fresh());

        $settings = app(SettingsService::class);
        $settings->set('document.ba_qr_enabled', '0', SettingsService::GROUP_DOCUMENT);

        $blocks = $service->signatureBlocks($document->fresh('signatures'));
        $filled = collect($blocks)->first(fn (array $block) => ! $block['placeholder']);

        $this->assertNull($filled['qr']);
        $this->assertNotNull($filled['signature']->name);
    }

    public function test_verification_page_shows_data_and_logs_scan(): void
    {
        $this->createVerifiedFinding();
        $this->approveAll();

        $document = app(BeritaAcaraService::class)->finalizeIfReady($this->application->fresh());
        $signature = $document->signatures->first();

        $response = $this->get(route('document-signature.verify', ['token' => $signature->qr_token]));

        $response->assertSuccessful();
        $response->assertSee($signature->name);
        $response->assertSee($this->application->application_number);
        $response->assertSee('Undang-Undang');

        $this->assertDatabaseHas('document_scan_logs', [
            'document_signature_id' => $signature->id,
        ]);
        $this->assertSame(1, DocumentScanLog::count());
    }

    public function test_verification_page_rejects_unknown_token(): void
    {
        $response = $this->get(route('document-signature.verify', ['token' => 'tidak-ada']));

        $response->assertSuccessful();
        $response->assertSee('tidak ditemukan', false);
        $this->assertDatabaseCount('document_scan_logs', 0);
    }

    public function test_download_serves_frozen_pdf_after_approvals(): void
    {
        $this->createVerifiedFinding();
        $this->approveAll();

        $response = $this->actingAs($this->owner)
            ->get(route('berita-acara.download', $this->application));

        $response->assertSuccessful();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertDatabaseCount('berita_acara_documents', 1);
    }
}
