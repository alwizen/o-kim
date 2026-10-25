<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Models\DocumentType;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Document\VehicleDocumentService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SEC-04: hardening unggahan berkas & respons pratinjau.
 */
class UploadHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $hsse;

    private Vehicle $vehicle;

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

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
    }

    private function stnkType(): DocumentType
    {
        return DocumentType::where('code', 'STNK')->firstOrFail();
    }

    private function makeApplication(): KimApplication
    {
        $application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        $application->update(['status' => ApplicationStatus::RevisionRequired]);

        return $application;
    }

    // ---------- Dokumen kendaraan ----------

    public function test_vehicle_document_rejects_disguised_extension(): void
    {
        $file = UploadedFile::fake()->create('shell.php', 10, 'image/png');

        $this->expectException(ValidationException::class);

        app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            $this->stnkType()->id,
            $file,
            ['expires_at' => now()->addYear()->toDateString()],
        );
    }

    public function test_vehicle_document_rejects_inactive_document_type(): void
    {
        $type = $this->stnkType();
        $type->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            $type->id,
            UploadedFile::fake()->create('stnk.pdf', 10, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );
    }

    public function test_vehicle_document_requires_expiry_when_document_type_requires_it(): void
    {
        $type = $this->stnkType();
        $this->assertTrue($type->has_expiry_date);

        $this->expectException(ValidationException::class);

        app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            $type->id,
            UploadedFile::fake()->create('stnk.pdf', 10, 'application/pdf'),
        );
    }

    public function test_preview_response_carries_security_headers(): void
    {
        $document = app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            $this->stnkType()->id,
            UploadedFile::fake()->create('stnk.pdf', 10, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );

        $document->update([
            'verification_status' => DocumentVerificationStatus::Verified,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('documents.preview', $document));

        $response->assertSuccessful();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    // ---------- Bukti pemeriksaan ----------

    public function test_inspection_attachment_rejects_disguised_extension(): void
    {
        $application = $this->makeApplication();
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        $application->update([
            'inspection_scheduled_at' => now()->subHour(),
        ]);

        $result = app(InspectionService::class)->recordResult(
            $application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        $this->expectException(ValidationException::class);

        app(InspectionAttachmentService::class)->upload(
            $result,
            $this->hsse,
            [UploadedFile::fake()->create('payload.html', 10, 'image/png')],
        );
    }

    // ---------- Tindak lanjut temuan ----------

    public function test_finding_action_rejects_disguised_extension(): void
    {
        $application = $this->makeApplication();

        $finding = Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->hsse->id,
        ]);

        $this->expectException(ValidationException::class);

        app(FindingService::class)->submitAction(
            $finding,
            $this->owner,
            'Sudah diganti',
            [UploadedFile::fake()->create('shell.php', 10, 'image/png')],
        );
    }

    public function test_finding_action_rejects_more_than_five_files(): void
    {
        $application = $this->makeApplication();

        $finding = Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->hsse->id,
        ]);

        $files = array_map(
            fn (int $i) => UploadedFile::fake()->create("bukti-{$i}.pdf", 10, 'application/pdf'),
            range(1, 6),
        );

        $this->expectException(ValidationException::class);

        app(FindingService::class)->submitAction($finding, $this->owner, 'Sudah diganti', $files);
    }

    public function test_finding_action_accepts_allowed_file(): void
    {
        $application = $this->makeApplication();

        $finding = Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->hsse->id,
        ]);

        $action = app(FindingService::class)->submitAction(
            $finding,
            $this->owner,
            'Sudah diganti',
            [UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')],
        );

        $this->assertSame(1, $action->attachments()->count());
        $this->assertSame(FindingStatus::SubmittedForVerification, $finding->fresh()->status);
    }
}
