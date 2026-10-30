<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\DispensationRequestStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\Findings\Pages\ListFindings;
use App\Filament\Transportir\Widgets\MyDispensationRequests;
use App\Filament\Transportir\Widgets\RejectedFollowUps;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\ApplicationTrendChart;
use App\Filament\Widgets\ExpiringDispensations;
use App\Filament\Widgets\FindingTrendChart;
use App\Filament\Widgets\FtmDecisionQueue;
use App\Filament\Widgets\FtmPendingDispensations;
use App\Filament\Widgets\FtmStatsOverview;
use App\Filament\Widgets\InspectionsDueToday;
use App\Filament\Widgets\PendingDocumentVerification;
use App\Filament\Widgets\PengawasApprovalQueue;
use App\Filament\Widgets\PengawasStatsOverview;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\TadInspectionQueue;
use App\Filament\Widgets\TadStatsOverview;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\DispensationService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Visibilitas dan isi widget dashboard per role.
 */
class WidgetsTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $admin;

    private User $tadHsse;

    private User $tadDistribusi;

    private User $pengawasHsse;

    private User $ftm;

    private Vehicle $vehicle;

    private KimApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');
        Storage::fake(config('media.disk'));
        Notification::fake();

        $this->transporter = Transporter::factory()->create();
        $this->owner = $this->userWith('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->admin = $this->userWith('admin_verifikasi');
        $this->tadHsse = $this->userWith('hsse');
        $this->tadDistribusi = $this->userWith('distribusi');
        $this->pengawasHsse = $this->userWith('pengawas_hsse');
        $this->ftm = $this->userWith('ftm');

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'B 1234 WGT',
        ]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(6)->toDateString());
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);
        $this->application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->admin->id,
        ]);
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    // ---------- Visibilitas per role ----------

    public function test_admin_stats_only_visible_to_admin_verifikasi_and_super_admin(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->admin);
        $this->assertTrue(AdminStatsOverview::canView());

        $this->actingAs($this->userWith('super_admin'));
        $this->assertTrue(AdminStatsOverview::canView());

        foreach ([$this->tadHsse, $this->pengawasHsse, $this->ftm] as $user) {
            $this->actingAs($user);
            $this->assertFalse(AdminStatsOverview::canView());
        }
    }

    public function test_tad_widgets_only_visible_to_inspectors(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->tadHsse);
        $this->assertTrue(TadStatsOverview::canView());
        $this->assertTrue(TadInspectionQueue::canView());
        $this->assertFalse(PengawasStatsOverview::canView());
        $this->assertFalse(FtmStatsOverview::canView());

        $this->actingAs($this->admin);
        $this->assertFalse(TadStatsOverview::canView());
        $this->assertFalse(TadInspectionQueue::canView());
    }

    public function test_pengawas_widgets_only_visible_to_approvers(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->pengawasHsse);
        $this->assertTrue(PengawasStatsOverview::canView());
        $this->assertTrue(PengawasApprovalQueue::canView());
        $this->assertFalse(TadStatsOverview::canView());
        $this->assertFalse(FtmStatsOverview::canView());
    }

    public function test_ftm_widgets_only_visible_to_ftm(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->ftm);
        $this->assertTrue(FtmStatsOverview::canView());
        $this->assertTrue(FtmDecisionQueue::canView());
        $this->assertTrue(ExpiringDispensations::canView());
        $this->assertTrue(FtmPendingDispensations::canView());
        $this->assertFalse(TadStatsOverview::canView());
        $this->assertFalse(PengawasStatsOverview::canView());

        $this->actingAs($this->userWith('super_admin'));
        $this->assertFalse(FtmStatsOverview::canView());
        $this->assertFalse(FtmDecisionQueue::canView());
    }

    public function test_admin_verification_widgets_visible_to_admin_only(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->admin);
        $this->assertTrue(PendingDocumentVerification::canView());
        $this->assertTrue(InspectionsDueToday::canView());

        $this->actingAs($this->ftm);
        $this->assertFalse(PendingDocumentVerification::canView());
        $this->assertFalse(InspectionsDueToday::canView());
    }

    public function test_chart_widgets_visible_to_ftm_and_super_admin(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->ftm);
        $this->assertTrue(ApplicationTrendChart::canView());
        $this->assertTrue(FindingTrendChart::canView());

        $this->actingAs($this->tadHsse);
        $this->assertFalse(ApplicationTrendChart::canView());
        $this->assertFalse(FindingTrendChart::canView());
    }

    public function test_recent_activity_visible_to_every_authenticated_user(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->tadHsse);
        $this->assertTrue(RecentActivity::canView());

        $this->actingAs($this->admin);
        $this->assertTrue(RecentActivity::canView());
    }

    // ---------- Isolasi data per fungsi ----------

    public function test_tad_inspection_queue_only_shows_own_department(): void
    {
        Filament::setCurrentPanel('admin');

        // HSSE diperiksa oleh TAD HSSE, Distribusi belum disentuh.
        $inspection = app(InspectionService::class);
        $hsseItems = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', Department::Hsse->value))
            ->where('is_active', true)->where('is_required', true)->get();

        foreach ($hsseItems as $item) {
            $inspection->recordResult(
                $this->application->fresh(),
                $item,
                InspectionResultValue::Compliant,
                $this->tadHsse,
            );
        }

        // HSSE sudah lengkap → TAD HSSE tidak melihat antrean.
        $this->actingAs($this->tadHsse);
        Livewire::test(TadInspectionQueue::class)
            ->assertCanNotSeeTableRecords([$this->application->fresh()]);

        // Distribusi belum lengkap → TAD Distribusi melihat antrean.
        $this->actingAs($this->tadDistribusi);
        Livewire::test(TadInspectionQueue::class)
            ->assertCanSeeTableRecords([$this->application->fresh()]);
    }

    // ---------- Ambang dispensasi ----------

    public function test_expiring_dispensations_uses_fourteen_day_threshold(): void
    {
        Filament::setCurrentPanel('admin');

        $finding = $this->makeFinding();

        $finding->update([
            'status' => FindingStatus::Dispensation,
            'dispensation_due_date' => Carbon::today()->addDays(10)->toDateString(),
            'dispensation_reason' => 'Menunggu suku cadang impor.',
        ]);

        $this->actingAs($this->ftm);
        Livewire::test(ExpiringDispensations::class)
            ->assertCanSeeTableRecords([$finding->fresh()]);

        // Di luar ambang 14 hari → tidak muncul.
        $finding->update(['dispensation_due_date' => Carbon::today()->addDays(20)->toDateString()]);

        Livewire::test(ExpiringDispensations::class)
            ->assertCanNotSeeTableRecords([$finding->fresh()]);
    }

    // ---------- Antrean FTM ----------

    public function test_ftm_decision_queue_shows_pending_requests_only(): void
    {
        Filament::setCurrentPanel('admin');

        $finding = $this->makeFinding();
        $request = app(DispensationService::class)->request(
            $finding,
            $this->owner,
            'Menunggu suku cadang impor.',
        );

        $this->actingAs($this->ftm);
        Livewire::test(FtmDecisionQueue::class)
            ->assertCanSeeTableRecords([$request->fresh()]);

        $request->update(['status' => DispensationRequestStatus::Rejected->value, 'decision_notes' => 'Tidak mendesak sama sekali.']);

        Livewire::test(FtmDecisionQueue::class)
            ->assertCanNotSeeTableRecords([$request->fresh()]);
    }

    // ---------- Transportir ----------

    public function test_transportir_widgets_visible_only_to_transportir(): void
    {
        Filament::setCurrentPanel('transportir');

        $this->actingAs($this->owner);
        $this->assertTrue(RejectedFollowUps::canView());
        $this->assertTrue(MyDispensationRequests::canView());

        $this->actingAs($this->admin);
        $this->assertFalse(RejectedFollowUps::canView());
    }

    public function test_rejected_follow_ups_only_shows_own_transporter(): void
    {
        Filament::setCurrentPanel('transportir');

        $finding = $this->makeFinding();
        $finding->update(['status' => FindingStatus::Rejected->value]);

        $this->actingAs($this->owner);
        Livewire::test(RejectedFollowUps::class)
            ->assertCanSeeTableRecords([$finding->fresh()]);

        $other = $this->userWith('transportir');
        $other->transporters()->attach(Transporter::factory()->create());

        $this->actingAs($other);
        Livewire::test(RejectedFollowUps::class)
            ->assertCanNotSeeTableRecords([$finding->fresh()]);
    }

    // ---------- Chart portabilitas ----------

    public function test_trend_charts_return_six_months_of_data(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->ftm);

        foreach ([ApplicationTrendChart::class, FindingTrendChart::class] as $chart) {
            Livewire::test($chart)
                ->assertSee('Tren');
        }
    }

    public function test_trend_charts_support_period_filter(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->ftm);

        foreach ([ApplicationTrendChart::class, FindingTrendChart::class] as $chart) {
            foreach (array_keys($chart::PERIODS) as $period) {
                Livewire::test($chart)
                    ->set('filter', $period)
                    ->assertOk();
            }
        }
    }

    public function test_chart_period_filter_changes_number_of_buckets(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->ftm);

        foreach ([1, 3, 6, 12] as $months) {
            $widget = new ApplicationTrendChart;
            $widget->filter = (string) $months;

            $data = (fn () => $this->getCachedData())->call($widget);

            $this->assertCount($months, $data['labels']);
            $this->assertCount($months, $data['datasets'][0]['data']);
        }
    }

    // ---------- Tabel temuan ----------

    public function test_findings_table_shows_vehicle_plate_column(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin);

        $finding = $this->makeFinding();

        Livewire::test(ListFindings::class)
            ->assertCanSeeTableRecords([$finding])
            ->assertSee('B 1234 WGT');
    }

    // ---------- Helper ----------

    private function makeFinding(): Finding
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', Department::Hsse->value))
            ->where('is_active', true)
            ->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->tadHsse,
            findingDescription: 'Selang apar foam retak',
        );

        return $this->application->fresh()->findings()->latest('id')->firstOrFail();
    }
}
