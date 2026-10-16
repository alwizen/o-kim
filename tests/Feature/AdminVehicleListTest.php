<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\DocumentsRelationManager;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminVehicleListTest extends TestCase
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

    public function test_admin_can_open_vehicle_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/vehicles')
            ->assertSuccessful();
    }

    public function test_vehicle_list_shows_vehicles_from_all_transporters(): void
    {
        $a = Transporter::factory()->create(['company_name' => 'PT Alfa']);
        $b = Transporter::factory()->create(['company_name' => 'PT Bravo']);
        Vehicle::factory()->create(['transporter_id' => $a->id, 'registration_number' => 'G 1111 AA']);
        Vehicle::factory()->create(['transporter_id' => $b->id, 'registration_number' => 'G 2222 BB']);

        $this->actingAs($this->admin())
            ->get('/admin/vehicles')
            ->assertSuccessful()
            ->assertSee('G 1111 AA')
            ->assertSee('G 2222 BB')
            ->assertSee('PT Alfa')
            ->assertSee('PT Bravo');
    }

    public function test_transportir_cannot_access_admin_vehicle_list(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $this->actingAs($user)->get('/admin/vehicles')->assertForbidden();
    }

    public function test_admin_vehicle_detail_shows_documents_and_preview_link(): void
    {
        Storage::fake('local');

        $transporter = Transporter::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => $transporter->id,
            'registration_number' => 'G 5555 PRV',
        ]);
        $type = DocumentType::factory()->create(['name' => 'STNK Pratinjau', 'code' => 'STNKPRV']);

        $path = 'vehicle-documents/'.$vehicle->id.'/stnk.pdf';
        Storage::disk('local')->put($path, 'dummy');

        $document = VehicleDocument::factory()->for($vehicle)->for($type)->create([
            'file_path' => $path,
            'original_filename' => 'stnk.pdf',
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/vehicles/'.$vehicle->getRouteKey())
            ->assertSuccessful()
            ->assertSee('G 5555 PRV')
            ->assertSee('Dokumen Kendaraan');

        // Tabel dokumen dimuat lazy via RelationManager — verifikasi lewat komponen Livewire.
        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => ViewVehicle::class,
        ])
            ->assertCanSeeTableRecords([$document])
            ->assertSee('STNK Pratinjau')
            ->assertSee('Pratinjau');
    }
}
