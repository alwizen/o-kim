<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\ListKimApplications as AdminListKimApplications;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication as AdminViewKimApplication;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Inspection\InspectionReportService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function recordHsseFinding(): void
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );
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
