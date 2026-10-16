<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Filament\Resources\Transporters\TransporterResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_global_search_is_enabled_on_both_panels(): void
    {
        $this->actingAs($this->admin());

        Filament::setCurrentPanel('admin');
        $this->assertNotNull(Filament::getCurrentPanel()->getGlobalSearchProvider());

        Filament::setCurrentPanel('transportir');
        $this->assertNotNull(Filament::getCurrentPanel()->getGlobalSearchProvider());
    }

    public function test_vehicle_resource_is_globally_searchable(): void
    {
        $this->actingAs($this->admin());

        $this->assertTrue(VehicleResource::canGloballySearch());
        $this->assertContains('registration_number', VehicleResource::getGloballySearchableAttributes());
        $this->assertContains('chassis_number', VehicleResource::getGloballySearchableAttributes());
    }

    public function test_application_resource_is_globally_searchable(): void
    {
        $this->actingAs($this->admin());

        $this->assertTrue(KimApplicationResource::canGloballySearch());
        $this->assertContains('application_number', KimApplicationResource::getGloballySearchableAttributes());
        $this->assertContains('vehicle.registration_number', KimApplicationResource::getGloballySearchableAttributes());
    }

    public function test_vehicle_search_by_nopol_returns_result(): void
    {
        $admin = $this->admin();
        $transporter = Transporter::factory()->create(['company_name' => 'PT Cari Cari']);
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => $transporter->id,
            'registration_number' => 'G 7777 CARI',
        ]);

        $this->actingAs($admin);

        $results = VehicleResource::getGlobalSearchResults('G 7777');

        $this->assertTrue(
            $results->contains(fn ($r) => $r->title === $vehicle->registration_number),
            'Kendaraan tidak ditemukan lewat global search.'
        );
    }

    public function test_vehicle_search_by_chassis_number_returns_result(): void
    {
        $admin = $this->admin();
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create()->id,
            'registration_number' => 'G 8888 RNG',
            'chassis_number' => 'MHRANGKA123456',
        ]);

        $this->actingAs($admin);

        $results = VehicleResource::getGlobalSearchResults('MHRANGKA');

        $this->assertTrue($results->contains(fn ($r) => $r->title === $vehicle->registration_number));
    }

    public function test_application_search_by_number_returns_result(): void
    {
        $admin = $this->admin();
        $transporter = Transporter::factory()->create();
        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        $application = KimApplication::factory()->create([
            'vehicle_id' => $vehicle->id,
            'transporter_id' => $transporter->id,
            'application_number' => 'APP/CARI/0009',
        ]);

        $this->actingAs($admin);

        $results = KimApplicationResource::getGlobalSearchResults('CARI/0009');

        $this->assertTrue($results->contains(fn ($r) => $r->title === $application->application_number));
    }

    public function test_search_results_respect_resource_authorization(): void
    {
        // FTM tidak punya manage_vehicles, tapi punya verify_documents → tetap boleh mencari kendaraan.
        $ftm = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $ftm->assignRole('ftm');

        $this->actingAs($ftm);
        $this->assertTrue(VehicleResource::canGloballySearch());

        // Tapi tidak boleh mencari pengguna internal.
        $this->assertFalse(UserResource::canGloballySearch());
    }

    public function test_transporter_resource_is_searchable_by_company_name(): void
    {
        $admin = $this->admin();
        $transporter = Transporter::factory()->create(['company_name' => 'PT Angkutan Cari']);
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');
        $user->transporters()->attach($transporter);

        $this->actingAs($admin);

        $this->assertTrue(TransporterResource::canGloballySearch());

        $results = TransporterResource::getGlobalSearchResults('PT Angkutan Cari');
        $this->assertTrue($results->isNotEmpty());
    }
}
