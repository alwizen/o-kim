<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Models\DocumentScanLog;
use App\Models\DocumentSignature;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use App\Services\Kim\KimPdfService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KimCardSignatureTest extends TestCase
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

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT KIM TTD']);
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
            'registration_number' => 'G 7777 KIM',
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

    private function completeInspections(): void
    {
        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas(
                'checklist',
                fn ($q) => $q->where('department', $department->value)->where('is_active', true)
            )->where('is_active', true)->get();

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

    private function issueCard(): KimCard
    {
        $this->completeInspections();
        $this->approveAll();

        return app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    public function test_kim_card_gets_five_signature_snapshots_on_issue(): void
    {
        $card = $this->issueCard();

        $this->assertSame(5, $card->signatures()->count());

        foreach (Department::approvalSteps() as $department) {
            $this->assertDatabaseHas('document_signatures', [
                'signable_type' => KimCard::class,
                'signable_id' => $card->id,
                'department' => $department->value,
            ]);
        }
    }

    public function test_signature_snapshot_carries_name_jabatan_ip_and_qr_token(): void
    {
        $card = $this->issueCard();

        /** @var DocumentSignature $ftm */
        $ftm = $card->signatures()
            ->where('department', Department::Ftm->value)
            ->firstOrFail();

        $this->assertSame($this->officers['ftm']->name, $ftm->name);
        $this->assertSame($this->officers['ftm']->jabatan, $ftm->jabatan);
        $this->assertNotNull($ftm->decided_at);
        $this->assertNotNull($ftm->ip_address);
        $this->assertSame(48, strlen($ftm->qr_token));
    }

    public function test_kim_pdf_is_frozen_with_hash_and_signatures(): void
    {
        $card = $this->issueCard();

        $path = app(KimPdfService::class)->generate($card);
        $card->refresh();

        $this->assertSame($path, $card->pdf_path);
        $this->assertNotNull($card->pdf_hash);
        $this->assertSame(64, strlen($card->pdf_hash));
        $this->assertTrue(Storage::disk(config('media.disk'))->exists($path));
    }

    public function test_signature_qr_verification_page_logs_scan_for_kim(): void
    {
        $card = $this->issueCard();
        $signature = $card->signatures()->firstOrFail();

        $response = $this->get(route('document-signature.verify', ['token' => $signature->qr_token]));

        $response->assertSuccessful();
        $response->assertSee($signature->name);
        $response->assertSee($card->kim_number);
        $response->assertSee('Kartu KIM');

        $this->assertDatabaseHas('document_scan_logs', [
            'document_type' => 'kim',
            'document_signature_id' => $signature->id,
        ]);
    }

    public function test_unknown_signature_token_is_not_logged(): void
    {
        $this->get(route('document-signature.verify', ['token' => 'tidak-ada']))
            ->assertSuccessful()
            ->assertSee('tidak ditemukan', false);

        $this->assertSame(0, DocumentScanLog::count());
    }
}
