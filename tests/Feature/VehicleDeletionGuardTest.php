<?php

namespace Tests\Feature;

use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Kendaraan dengan riwayat pengajuan/KIM tidak boleh dihapus, dan riwayat
 * pengajuan tetap dapat menampilkan kendaraan walau kendaraannya terhapus.
 */
class VehicleDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function vehicleWithApplication(): Vehicle
    {
        $transporter = Transporter::factory()->create();
        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        KimApplication::factory()->create([
            'transporter_id' => $transporter->id,
            'vehicle_id' => $vehicle->id,
        ]);

        return $vehicle;
    }

    public function test_vehicle_with_kim_history_cannot_be_deleted(): void
    {
        $vehicle = $this->vehicleWithApplication();

        try {
            $vehicle->delete();
            $this->fail('Kendaraan dengan riwayat KIM seharusnya tidak dapat dihapus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vehicle', $e->errors());
        }

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'deleted_at' => null]);
    }

    public function test_vehicle_without_kim_history_can_be_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();

        $vehicle->delete();

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id, 'deleted_at' => null]);
    }

    public function test_application_relation_still_resolves_soft_deleted_vehicle(): void
    {
        $vehicle = $this->vehicleWithApplication();

        // Paksa soft delete langsung (melewati guard) untuk mensimulasikan data lama.
        Vehicle::withoutEvents(fn () => $vehicle->delete());

        $application = KimApplication::where('vehicle_id', $vehicle->id)->firstOrFail();

        $this->assertNotNull($application->vehicle);
        $this->assertTrue($application->vehicle->trashed());
        $this->assertSame($vehicle->registration_number, $application->vehicle->registration_number);
    }
}
