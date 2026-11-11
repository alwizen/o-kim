<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\ListKimApplications as AdminListKimApplications;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication as AdminViewKimApplication;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Inspection\InspectionReportService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionReportTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $hsse;

    private Vehicle $vehicle;

    private KimApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT Transporter Utama']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 1234 TGL',
        ]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);
        $this->application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subHour(),
        ]);
    }

    private function recordHsseFinding(): Finding
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        $result = app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        return $result->load('finding')->finding;
    }

    /**
     * @return array<string, mixed>
     */
    private function hsseFindingRow(): array
    {
        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());
        $hsse = collect($report['departments'])
            ->first(fn (array $department) => $department['department'] === Department::Hsse);

        return collect($hsse['rows'])->first(fn (array $row) => $row['finding'] !== null);
    }

    public function test_report_filename_has_on_proses_tag_when_checklist_incomplete(): void
    {
        $this->recordHsseFinding();

        $filename = app(InspectionReportService::class)->filename($this->application->fresh());

        $this->assertStringContainsString('[ON-PROSES]', $filename);
        $this->assertStringNotContainsString('[DONE]', $filename);
    }

    public function test_report_filename_has_done_tag_when_all_items_assessed(): void
    {
        $service = app(InspectionService::class);

        $inspectors = [
            'hsse' => $this->hsse,
            'distribusi' => tap(User::factory()->create(['account_status' => AccountStatus::Approved]), fn (User $u) => $u->assignRole('distribusi')),
            'qq' => tap(User::factory()->create(['account_status' => AccountStatus::Approved]), fn (User $u) => $u->assignRole('qq')),
        ];

        InspectionItem::whereHas('checklist', fn ($q) => $q->whereIn('department', array_keys($inspectors)))
            ->with('checklist')
            ->get()
            ->each(fn (InspectionItem $item) => $service->recordResult(
                $this->application->fresh(),
                $item,
                InspectionResultValue::Compliant,
                $inspectors[$item->checklist->department->value],
            ));

        $filename = app(InspectionReportService::class)->filename($this->application->fresh());

        $this->assertStringContainsString('[DONE]', $filename);
        $this->assertStringNotContainsString('[ON-PROSES]', $filename);
    }

    public function test_transporter_owner_can_download_inspection_report_pdf(): void
    {
        $this->recordHsseFinding();

        $response = $this->actingAs($this->owner)
            ->get(route('inspection-report.download', $this->application));

        $response->assertSuccessful();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Laporan-Pemeriksaan-', (string) $response->headers->get('content-disposition'));
    }

    public function test_transporter_owner_can_preview_inspection_report_inline(): void
    {
        $this->recordHsseFinding();

        $response = $this->actingAs($this->owner)
            ->get(route('inspection-report.preview', $this->application));

        $response->assertSuccessful();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_other_transporter_cannot_download_report(): void
    {
        $otherTransporter = Transporter::factory()->create();
        $otherUser = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $otherUser->assignRole('transportir');
        $otherUser->transporters()->attach($otherTransporter);

        $this->actingAs($otherUser)
            ->get(route('inspection-report.download', $this->application))
            ->assertForbidden();
    }

    public function test_admin_with_view_applications_permission_can_download_report(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('admin_verifikasi');

        $this->actingAs($admin)
            ->get(route('inspection-report.download', $this->application))
            ->assertSuccessful();
    }

    public function test_unauthenticated_user_cannot_access_report(): void
    {
        $this->get(route('inspection-report.download', $this->application))
            ->assertRedirect(route('login'));
    }

    public function test_print_action_visible_on_transportir_application_view_when_results_exist(): void
    {
        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->owner);

        // Belum ada hasil inspeksi -> tombol cetak belum muncul
        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('inspection_report');

        // Setelah ada hasil inspeksi -> tombol cetak muncul
        $this->recordHsseFinding();

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionVisible('inspection_report');
    }

    public function test_print_action_visible_on_admin_application_view_when_results_exist(): void
    {
        Filament::setCurrentPanel('admin');

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('admin_verifikasi');
        $this->actingAs($admin);

        Livewire::test(AdminViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('inspection_report');

        $this->recordHsseFinding();

        Livewire::test(AdminViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionVisible('inspection_report');
    }

    public function test_print_action_visible_on_admin_application_table_when_results_exist(): void
    {
        Filament::setCurrentPanel('admin');

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('admin_verifikasi');
        $this->actingAs($admin);

        Livewire::test(AdminListKimApplications::class)
            ->assertTableActionHidden('inspection_report', $this->application);

        $this->recordHsseFinding();

        Livewire::test(AdminListKimApplications::class)
            ->assertTableActionVisible('inspection_report', $this->application);
    }

    public function test_department_meta_carries_inspector_name_jabatan_and_time(): void
    {
        $this->hsse->update(['jabatan' => 'Safety Officer']);

        $this->recordHsseFinding();

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());
        $hsse = collect($report['departments'])
            ->first(fn (array $department) => $department['department'] === Department::Hsse);

        $this->assertCount(1, $hsse['inspectors']);
        $this->assertSame($this->hsse->name, $hsse['inspectors'][0]['name']);
        $this->assertSame('Safety Officer', $hsse['inspectors'][0]['jabatan']);
        $this->assertNotNull($hsse['inspectors'][0]['inspected_at']);
    }

    public function test_row_carries_notes_finding_and_photos(): void
    {
        $this->recordHsseFinding();

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());
        $hsse = collect($report['departments'])
            ->first(fn (array $department) => $department['department'] === Department::Hsse);
        $row = collect($hsse['rows'])->first(fn (array $row) => $row['result'] !== null);

        $this->assertNotNull($row['finding']);
        $this->assertSame('APAR kedaluwarsa', $row['finding']->description);
        $this->assertArrayHasKey('notes', $row);
        $this->assertArrayHasKey('photos', $row);
    }

    public function test_row_follow_up_status_is_belum_ditindaklanjuti_without_action(): void
    {
        $this->recordHsseFinding();

        $row = $this->hsseFindingRow();

        $this->assertSame('Belum ditindaklanjuti', $row['follow_up']['label']);
        $this->assertSame('badge-finding', $row['follow_up']['class']);
    }

    public function test_row_follow_up_status_waits_for_verification_after_action_submitted(): void
    {
        $finding = $this->recordHsseFinding();

        app(FindingService::class)->submitAction($finding, $this->owner, 'Selang sudah diganti.');

        $row = $this->hsseFindingRow();

        $this->assertSame('Menunggu verifikasi', $row['follow_up']['label']);
        $this->assertSame('badge-empty', $row['follow_up']['class']);
    }

    public function test_row_follow_up_status_is_accepted_after_verification(): void
    {
        $finding = $this->recordHsseFinding();
        $action = app(FindingService::class)->submitAction($finding, $this->owner, 'Selang sudah diganti.');

        app(FindingService::class)->verifyAction($finding->fresh(), $action, $this->hsse, true);

        $row = $this->hsseFindingRow();

        $this->assertSame('Tindak lanjut diterima', $row['follow_up']['label']);
        $this->assertSame('badge-compliant', $row['follow_up']['class']);
    }

    public function test_row_follow_up_status_is_rejected_after_rejected_verification(): void
    {
        $finding = $this->recordHsseFinding();
        $action = app(FindingService::class)->submitAction($finding, $this->owner, 'Selang sudah diganti.');

        app(FindingService::class)->verifyAction($finding->fresh(), $action, $this->hsse, false, 'Foto belum jelas.');

        $row = $this->hsseFindingRow();

        $this->assertSame('Tindak lanjut ditolak', $row['follow_up']['label']);
        $this->assertSame('badge-finding', $row['follow_up']['class']);
    }

    public function test_report_view_shows_only_follow_up_status_without_submitter_or_time(): void
    {
        $finding = $this->recordHsseFinding();
        $action = app(FindingService::class)->submitAction($finding, $this->owner, 'Selang sudah diganti.');
        app(FindingService::class)->verifyAction($finding->fresh(), $action, $this->hsse, true);

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());
        $html = view('reports.inspection-checklist', [
            'application' => $this->application->fresh(),
            'departments' => $report['departments'],
            'summary' => $report['summary'],
            'printedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Tindak lanjut diterima', $html);
        $this->assertStringNotContainsString('Dikirim', $html);
        $this->assertStringNotContainsString($this->owner->name.'</', $html);
    }

    public function test_report_pdf_renders_without_timestamp_for_rows_without_finding(): void
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
            notes: 'Kondisi baik',
        );

        $pdf = app(InspectionReportService::class)->render($this->application->fresh())->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_row_photos_are_embedded_as_data_uri(): void
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        $result = app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'Selang retak',
        );

        Storage::fake(config('media.disk'));

        app(InspectionAttachmentService::class)->upload(
            $result,
            $this->hsse,
            [UploadedFile::fake()->image('bukti.jpg', 200, 150)],
        );

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());
        $hsse = collect($report['departments'])
            ->first(fn (array $department) => $department['department'] === Department::Hsse);
        $row = collect($hsse['rows'])->first(fn (array $row) => $row['result'] !== null);

        $this->assertNotEmpty($row['photos']);
        $this->assertStringStartsWith('data:image/', $row['photos'][0]);
    }

    public function test_report_view_hides_time_when_there_is_no_finding(): void
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
            notes: 'Kondisi baik',
        );

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());

        $html = view('reports.inspection-checklist', [
            'application' => $this->application->fresh(),
            'departments' => $report['departments'],
            'summary' => $report['summary'],
            'printedAt' => now(),
        ])->render();

        // Nama petugas hanya muncul pada baris meta fungsi, bukan per butir.
        $this->assertSame(1, substr_count($html, $this->hsse->name));

        // Kolom foto tidak memuat waktu karena butir ini tidak bertemuan.
        $this->assertStringNotContainsString('class="photo-time"', $html);
    }

    public function test_report_view_shows_time_in_photo_column_only_for_findings(): void
    {
        $this->recordHsseFinding();

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());

        $html = view('reports.inspection-checklist', [
            'application' => $this->application->fresh(),
            'departments' => $report['departments'],
            'summary' => $report['summary'],
            'printedAt' => now(),
        ])->render();

        // Butir bertemuan menampilkan waktu di bawah kolom foto.
        $this->assertStringContainsString('class="photo-time"', $html);
    }

    public function test_report_service_builds_accurate_summary(): void
    {
        $this->recordHsseFinding();

        $report = app(InspectionReportService::class)->buildReport($this->application->fresh());

        $this->assertSame(1, $report['summary']['assessed_items']);
        $this->assertSame(1, $report['summary']['finding']);
        $this->assertSame(0, $report['summary']['compliant']);
        $this->assertSame(1, $report['summary']['open_findings']);
        $this->assertGreaterThan(0, $report['summary']['total_items']);
        $this->assertNotEmpty($report['departments']);
    }
}
