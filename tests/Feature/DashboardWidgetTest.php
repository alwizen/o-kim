<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DocumentVerificationStatus;
use App\Filament\Transportir\Widgets\TransportirExpiringDocumentsTable;
use App\Filament\Transportir\Widgets\TransportirStatsOverview;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\ExpiringDocumentsTable;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Document\DocumentExpiryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $transportir;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($this->transporter);

        $this->admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->admin->assignRole('super_admin');
    }

    // ---------- DocumentExpiryService ----------

    public function test_expiry_service_detects_expiring_document(): void
    {
        $vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        VehicleDocument::factory()->for($vehicle)->expiresIn(10)->create();

        $this->assertSame(1, app(DocumentExpiryService::class)->expiringCount());
    }

    public function test_expiry_service_detects_expired_document(): void
    {
        $vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        VehicleDocument::factory()->for($vehicle)->expired()->create();

        $expiry = app(DocumentExpiryService::class);

        $this->assertSame(1, $expiry->expiredCount());
        $this->assertSame(0, $expiry->expiringCount());
    }

    public function test_expiry_service_ignores_far_future_documents(): void
    {
        $vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        VehicleDocument::factory()->for($vehicle)->expiresIn(300)->create();

        $expiry = app(DocumentExpiryService::class);

        $this->assertSame(0, $expiry->expiringCount());
        $this->assertSame(0, $expiry->expiredCount());
    }

    public function test_expiry_service_ignores_replaced_documents(): void
    {
        $vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        $new = VehicleDocument::factory()->for($vehicle)->expiresIn(300)->create();
        VehicleDocument::factory()->for($vehicle)->expiresIn(10)->create(['replaced_by_id' => $new->id]);

        $this->assertSame(0, app(DocumentExpiryService::class)->expiringCount());
    }

    public function test_expiry_service_ignores_rejected_documents(): void
    {
        $vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        VehicleDocument::factory()->for($vehicle)->expiresIn(10)->create([
            'verification_status' => DocumentVerificationStatus::Rejected,
        ]);

        $this->assertSame(0, app(DocumentExpiryService::class)->expiringCount());
    }

    public function test_expiry_query_is_scoped_to_selected_transporter(): void
    {
        $ownVehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
        VehicleDocument::factory()->for($ownVehicle)->expiresIn(10)->create();

        $otherTransporter = Transporter::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['transporter_id' => $otherTransporter->id]);
        VehicleDocument::factory()->for($otherVehicle)->expiresIn(10)->create();

        $expiry = app(DocumentExpiryService::class);

        $this->assertSame(2, $expiry->expiringCount());
        $this->assertSame(1, $expiry->expiringCount([$this->transporter->id]));
        $this->assertSame(1, $expiry->expiringCount([$otherTransporter->id]));
    }

    // ---------- Widget stats (render langsung) ----------

    public function test_admin_stats_widget_counts_pending_registrations_and_open_findings(): void
    {
        User::factory()->count(2)->create(['account_status' => AccountStatus::Pending])
            ->each(fn ($user) => $user->assignRole('transportir'));

        $this->actingAs($this->admin);

        Livewire::test(AdminStatsOverview::class)
            ->assertSee('Pendaftaran Menunggu Verifikasi')
            ->assertSee('Temuan Terbuka')
            ->assertSee('KIM Aktif')
            ->assertSee('Dokumen Mendekati Kedaluwarsa')
            ->assertSee('2');
    }

    public function test_transportir_stats_widget_counts_own_vehicles_only(): void
    {
        Vehicle::factory()->count(3)->create(['transporter_id' => $this->transporter->id]);

        $otherTransporter = Transporter::factory()->create();
        Vehicle::factory()->count(5)->create(['transporter_id' => $otherTransporter->id]);

        $this->actingAs($this->transportir);

        Livewire::test(TransportirStatsOverview::class)
            ->assertSee('Total Kendaraan')
            ->assertSee('Pengajuan Berjalan')
            ->assertSee('KIM Mendekati Kedaluwarsa')
            ->assertSee('3')
            ->assertDontSee('Pendaftaran Menunggu Verifikasi');
    }

    // ---------- Widget tabel peringatan dokumen ----------

    public function test_transportir_expiring_table_lists_own_vehicle_document(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 9999 EXP',
        ]);
        $type = DocumentType::factory()->create(['name' => 'STNK Uji', 'code' => 'STNKUJI']);
        VehicleDocument::factory()->for($vehicle)->for($type)->expiresIn(5)->create();

        $this->actingAs($this->transportir);

        Livewire::test(TransportirExpiringDocumentsTable::class)
            ->assertCanSeeTableRecords(VehicleDocument::all())
            ->assertSee('G 9999 EXP')
            ->assertSee('STNK Uji');
    }

    public function test_transportir_expiring_table_hides_other_company_documents(): void
    {
        $otherTransporter = Transporter::factory()->create();
        $otherVehicle = Vehicle::factory()->create([
            'transporter_id' => $otherTransporter->id,
            'registration_number' => 'G 7777 OTH',
        ]);
        $foreign = VehicleDocument::factory()->for($otherVehicle)->expiresIn(5)->create();

        $this->actingAs($this->transportir);

        Livewire::test(TransportirExpiringDocumentsTable::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_admin_expiring_table_shows_company_name(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 8888 ADM',
        ]);
        VehicleDocument::factory()->for($vehicle)->expiresIn(3)->create();

        $this->actingAs($this->admin);

        Livewire::test(ExpiringDocumentsTable::class)
            ->assertCanSeeTableRecords(VehicleDocument::all())
            ->assertSee('G 8888 ADM')
            ->assertSee($this->transporter->company_name);
    }

    // ---------- Halaman dashboard ----------

    public function test_admin_dashboard_is_accessible(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSuccessful();
    }

    public function test_transportir_dashboard_is_accessible(): void
    {
        $this->actingAs($this->transportir)->get('/transportir')->assertSuccessful();
    }

    public function test_widget_visibility_differs_per_panel(): void
    {
        $this->actingAs($this->admin);
        $this->assertTrue(AdminStatsOverview::canView());
        $this->assertFalse(TransportirStatsOverview::canView());

        auth()->logout();
        $this->actingAs($this->transportir);
        $this->assertTrue(TransportirStatsOverview::canView());
        $this->assertFalse(AdminStatsOverview::canView());
    }
}
