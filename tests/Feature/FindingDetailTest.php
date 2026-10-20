<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\Findings\Pages\ListFindings;
use App\Filament\Resources\Findings\Pages\ViewFinding as AdminViewFinding;
use App\Filament\Transportir\Resources\Findings\Pages\ViewFinding as TransportirViewFinding;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\FindingActionAttachment;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Inspection\InspectionAttachmentService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FindingDetailTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $otherTransportir;

    private User $admin;

    private User $hsse;

    private Vehicle $vehicle;

    private KimApplication $application;

    private Finding $finding;

    private FindingActionAttachment $attachment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT Temuan Uji']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $otherTransporter = Transporter::factory()->create();
        $this->otherTransportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->otherTransportir->assignRole('transportir');
        $this->otherTransportir->transporters()->attach($otherTransporter);

        $this->admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->admin->assignRole('super_admin');

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 1234 TMD',
        ]);

        $this->application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);
        $this->application->update(['status' => ApplicationStatus::RevisionRequired]);

        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        $this->finding = Finding::create([
            'kim_application_id' => $this->application->id,
            'department' => Department::Hsse,
            'description' => 'APAR kedaluwarsa',
            'status' => FindingStatus::Open,
            'created_by' => $this->hsse->id,
        ]);

        // Hubungkan temuan ke butir checklist agar "jenis temuan" terisi.
        $this->finding->inspectionResult()->associate(
            $this->application->inspectionResults()->create([
                'inspection_item_id' => $item->id,
                'inspected_by' => $this->hsse->id,
                'result' => InspectionResultValue::Finding,
                'inspected_at' => now(),
            ])
        )->save();

        // Lampiran dokumentasi temuan dari petugas (hasil pemeriksaan).
        app(InspectionAttachmentService::class)->upload(
            $this->finding->inspectionResult,
            $this->hsse,
            [UploadedFile::fake()->image('dokumentasi.jpg', 640, 480)],
        );

        $action = FindingAction::create([
            'finding_id' => $this->finding->id,
            'submitted_by' => $this->owner->id,
            'description' => 'APAR sudah diganti',
        ]);

        Storage::disk('local')->put("finding-actions/{$action->id}/bukti.pdf", 'dummy');

        $this->attachment = FindingActionAttachment::create([
            'finding_action_id' => $action->id,
            'file_path' => "finding-actions/{$action->id}/bukti.pdf",
            'original_filename' => 'bukti.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 204800,
        ]);
    }

    // ---------- Akses berkas ----------

    public function test_owner_can_preview_attachment(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('finding-attachments.preview', $this->attachment));

        $response->assertSuccessful();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition') ?? '');
    }

    public function test_owner_can_download_attachment(): void
    {
        $this->actingAs($this->owner)
            ->get(route('finding-attachments.download', $this->attachment))
            ->assertSuccessful();
    }

    public function test_other_transportir_cannot_access_attachment(): void
    {
        $this->actingAs($this->otherTransportir)
            ->get(route('finding-attachments.preview', $this->attachment))
            ->assertForbidden();

        $this->actingAs($this->otherTransportir)
            ->get(route('finding-attachments.download', $this->attachment))
            ->assertForbidden();
    }

    public function test_admin_can_access_attachment(): void
    {
        $this->actingAs($this->admin)
            ->get(route('finding-attachments.preview', $this->attachment))
            ->assertSuccessful();
    }

    public function test_guest_cannot_access_attachment(): void
    {
        $this->get(route('finding-attachments.download', $this->attachment))
            ->assertRedirect();
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::disk('local')->delete($this->attachment->file_path);

        $this->actingAs($this->owner)
            ->get(route('finding-attachments.download', $this->attachment))
            ->assertNotFound();
    }

    // ---------- Halaman temuan ----------

    public function test_transportir_finding_page_shows_attachment_name_and_actions(): void
    {
        $this->actingAs($this->owner);

        Filament::setCurrentPanel('transportir');

        Livewire::test(TransportirViewFinding::class, ['record' => $this->finding->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Dokumentasi Temuan')
            ->assertSee('dokumentasi.jpg')
            ->assertSee('bukti.pdf')
            ->assertSee('Pratinjau')
            ->assertSee('Unduh');
    }

    public function test_admin_finding_page_shows_attachment_name_and_actions(): void
    {
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('admin');

        Livewire::test(AdminViewFinding::class, ['record' => $this->finding->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('bukti.pdf')
            ->assertSee('Pratinjau')
            ->assertSee('Unduh');
    }

    // ---------- Temuan pada pengajuan ----------

    public function test_transportir_application_page_lists_finding_with_checklist_type(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('APAR kedaluwarsa')
            ->assertSee('HSSE-01');
    }

    public function test_admin_application_page_lists_finding_with_checklist_type(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Resources\KimApplications\Pages\ViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('APAR kedaluwarsa')
            ->assertSee('HSSE-01');
    }

    // ---------- Tabel daftar temuan ----------

    public function test_transportir_findings_table_shows_checklist_type(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(\App\Filament\Transportir\Resources\Findings\Pages\ListFindings::class)
            ->assertCanSeeTableRecords([$this->finding])
            ->assertSee('HSSE-01');
    }

    public function test_admin_findings_table_shows_checklist_type(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListFindings::class)
            ->assertCanSeeTableRecords([$this->finding])
            ->assertSee('HSSE-01');
    }
}
