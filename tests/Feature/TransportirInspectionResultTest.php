<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\InspectionItem;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransportirInspectionResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_hasil_pemeriksaan_shows_department_and_inspector(): void
    {
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);

        $transporter = Transporter::factory()->create();
        $owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $owner->assignRole('transportir');
        $owner->transporters()->attach($transporter);

        $hsse = User::factory()->create(['account_status' => AccountStatus::Approved, 'name' => 'Petugas HSSE Uji']);
        $hsse->assignRole('hsse');

        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($vehicle, $code);
        }

        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($application, $owner);
        $application->update(['inspection_scheduled_at' => now()->subDay(), 'inspection_scheduled_by' => $hsse->id]);

        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();
        app(InspectionService::class)->recordResult(
            $application->fresh(), $item, InspectionResultValue::Compliant, $hsse,
        );

        Filament::setCurrentPanel('transportir');
        $this->actingAs($owner);

        Livewire::test(ViewKimApplication::class, ['record' => $application->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Fungsi')
            ->assertSee('HSSE (TAD)')
            ->assertSee('Diperiksa Oleh')
            // Format "Diperiksa Oleh": nama pemeriksa disertai role-nya.
            ->assertSee('Petugas HSSE Uji (HSSE (TAD))');
    }
}
