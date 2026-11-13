<?php

namespace Tests\Feature;

use App\Actions\Vehicle\ActivateVehicle;
use App\Actions\Vehicle\DeactivateVehicle;
use App\Enums\AccountStatus;
use App\Enums\VehicleStatus;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Models\ActivityLog;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Super admin dapat menonaktifkan/mengaktifkan kendaraan dari halaman detail,
 * sementara petugas lain (mis. verifikator dokumen) tidak dapat.
 */
class VehicleStatusActionTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $transporter = Transporter::factory()->create();
        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $transporter->id,
            'status' => VehicleStatus::Active,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    public function test_deactivate_action_marks_vehicle_inactive_and_logs(): void
    {
        app(DeactivateVehicle::class)->execute($this->vehicle);

        $this->assertSame(VehicleStatus::Inactive, $this->vehicle->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'vehicle.deactivated',
            'subject_id' => $this->vehicle->id,
        ]);
    }

    public function test_activate_action_marks_vehicle_active_and_logs(): void
    {
        $this->vehicle->update(['status' => VehicleStatus::Inactive]);

        app(ActivateVehicle::class)->execute($this->vehicle);

        $this->assertSame(VehicleStatus::Active, $this->vehicle->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'vehicle.activated',
            'subject_id' => $this->vehicle->id,
        ]);
    }

    public function test_super_admin_sees_deactivate_and_can_change_status(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ViewVehicle::class, ['record' => $this->vehicle->getRouteKey()])
            ->assertActionVisible('deactivate')
            ->assertActionHidden('activate')
            ->callAction('deactivate')
            ->assertHasNoActionErrors();

        $this->assertSame(VehicleStatus::Inactive, $this->vehicle->fresh()->status);
        $this->assertSame(1, ActivityLog::where('event', 'vehicle.deactivated')->count());
    }

    public function test_super_admin_sees_activate_for_inactive_vehicle(): void
    {
        Filament::setCurrentPanel('admin');
        $this->vehicle->update(['status' => VehicleStatus::Inactive]);

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ViewVehicle::class, ['record' => $this->vehicle->getRouteKey()])
            ->assertActionVisible('activate')
            ->assertActionHidden('deactivate')
            ->callAction('activate')
            ->assertHasNoActionErrors();

        $this->assertSame(VehicleStatus::Active, $this->vehicle->fresh()->status);
    }

    public function test_non_super_admin_cannot_see_status_actions(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($this->userWithRole('admin_verifikasi'))
            ->test(ViewVehicle::class, ['record' => $this->vehicle->getRouteKey()])
            ->assertActionHidden('deactivate')
            ->assertActionHidden('activate');
    }
}
