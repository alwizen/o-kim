<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Transportir\Resources\Vehicles\Pages\CreateVehicle;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function transportir(): User
    {
        $transporter = Transporter::factory()->create();
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');
        $user->transporters()->attach($transporter);

        return $user;
    }

    public function test_new_detail_fields_are_nullable(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create()->id,
            'chassis_number' => null,
            'engine_number' => null,
            'transport_type' => null,
            'tank_material' => null,
        ]);

        $this->assertNull($vehicle->chassis_number);
        $this->assertNull($vehicle->engine_number);
        $this->assertNull($vehicle->transport_type);
        $this->assertNull($vehicle->tank_material);
    }

    public function test_detail_fields_can_be_stored(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create()->id,
            'chassis_number' => 'MH1234567890',
            'engine_number' => 'ENG987654',
            'transport_type' => 'BBM',
            'tank_material' => 'Baja Karbon',
        ]);

        $vehicle->refresh();

        $this->assertSame('MH1234567890', $vehicle->chassis_number);
        $this->assertSame('ENG987654', $vehicle->engine_number);
        $this->assertSame('BBM', $vehicle->transport_type);
        $this->assertSame('Baja Karbon', $vehicle->tank_material);
    }

    public function test_transportir_can_create_vehicle_with_details(): void
    {
        $this->actingAs($this->transportir());

        Filament::setCurrentPanel(
            Filament::getPanel('transportir')
        );

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'registration_number' => 'G 4321 DTL',
                'brand' => 'Hino',
                'model' => 'semi_trailer',
                'manufacture_year' => 2022,
                'tank_capacity' => 24000,
                'chassis_number' => 'MH9876543210',
                'engine_number' => 'ENG123456',
                'transport_type' => 'chemical',
                'tank_material' => 'stainless_steel',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vehicles', [
            'registration_number' => 'G 4321 DTL',
            'chassis_number' => 'MH9876543210',
            'engine_number' => 'ENG123456',
            'transport_type' => 'chemical',
            'tank_material' => 'stainless_steel',
        ]);
    }

    public function test_admin_vehicle_detail_shows_new_fields(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create()->id,
            'chassis_number' => 'MH111222333',
            'engine_number' => 'ENG444555',
            'transport_type' => 'BBM',
            'tank_material' => 'Aluminium',
        ]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/vehicles/'.$vehicle->getRouteKey())
            ->assertSuccessful()
            ->assertSee('MH111222333')
            ->assertSee('ENG444555')
            ->assertSee('BBM')
            ->assertSee('Aluminium');
    }

    public function test_transportir_can_create_vehicle_with_karoseri_fields(): void
    {
        $this->actingAs($this->transportir());

        Filament::setCurrentPanel(
            Filament::getPanel('transportir')
        );

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'registration_number' => 'G 5555 KRS',
                'tank_capacity' => 16000,
                'transport_type' => 'chemical',
                'karoseri_builder' => 'PT Karoseri Maju',
                'karoseri_built_period' => '03/2021',
                'tank_wall_thickness_mm' => '5.5',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $vehicle = Vehicle::where('registration_number', 'G 5555 KRS')->firstOrFail();

        $this->assertSame('PT Karoseri Maju', $vehicle->karoseri_builder);
        $this->assertSame('03/2021', $vehicle->karoseri_built_period);
        $this->assertSame('5.5', (string) $vehicle->tank_wall_thickness_mm);
    }

    public function test_karoseri_fields_are_nullable(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create()->id,
            'karoseri_builder' => null,
            'karoseri_built_period' => null,
            'tank_wall_thickness_mm' => null,
        ]);

        $vehicle->refresh();

        $this->assertNull($vehicle->karoseri_builder);
        $this->assertNull($vehicle->karoseri_built_period);
        $this->assertNull($vehicle->tank_wall_thickness_mm);
    }

    public function test_karoseri_built_period_must_match_mm_yyyy_format(): void
    {
        $this->actingAs($this->transportir());

        Filament::setCurrentPanel(
            Filament::getPanel('transportir')
        );

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'registration_number' => 'G 6666 KRS',
                'tank_capacity' => 16000,
                'transport_type' => 'chemical',
                'karoseri_built_period' => 'Maret 2021',
            ])
            ->call('create')
            ->assertHasFormErrors(['karoseri_built_period']);
    }

    public function test_admin_vehicle_detail_shows_karoseri_fields(): void
    {
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create()->id,
            'karoseri_builder' => 'PT Karoseri Karya',
            'karoseri_built_period' => '12/2019',
            'tank_wall_thickness_mm' => 6,
        ]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/vehicles/'.$vehicle->getRouteKey())
            ->assertSuccessful()
            ->assertSee('PT Karoseri Karya')
            ->assertSee('12/2019')
            ->assertSee('6 mm');
    }
}
