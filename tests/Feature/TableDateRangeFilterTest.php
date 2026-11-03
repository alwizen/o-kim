<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Filament\Resources\Findings\Pages\ListFindings as AdminListFindings;
use App\Filament\Resources\KimApplications\Pages\ListKimApplications as AdminListKimApplications;
use App\Filament\Transportir\Resources\Findings\Pages\ListFindings as TransportirListFindings;
use App\Models\Finding;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TableDateRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);

        $transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($transporter);
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }
    }

    private function makeApplication(string $submittedAt): KimApplication
    {
        // Kendaraan hanya boleh punya satu pengajuan berjalan, jadi pakai kendaraan baru.
        $vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->vehicle->transporter_id,
        ]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($vehicle, $code);
        }

        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($application, $this->owner);

        $application->update(['submitted_at' => $submittedAt]);

        return $application->refresh();
    }

    private function makeFinding(KimApplication $application, string $createdAt): Finding
    {
        $finding = Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse->value,
            'description' => 'Temuan uji filter tanggal',
            'status' => 'open',
            'created_by' => $this->owner->id,
        ]);

        $finding->forceFill(['created_at' => $createdAt])->save();

        return $finding->refresh();
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
    }

    public function test_kim_applications_filter_by_submitted_date_range(): void
    {
        $this->actingAsAdmin();
        Filament::setCurrentPanel('admin');

        $inside = $this->makeApplication('2025-03-10 09:00:00');
        $outside = $this->makeApplication('2025-06-10 09:00:00');

        Livewire::test(AdminListKimApplications::class)
            ->filterTable('submitted_at', [
                'submitted_from' => '2025-03-01',
                'submitted_until' => '2025-03-31',
            ])
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$outside]);
    }

    public function test_kim_applications_filter_accepts_open_ended_range(): void
    {
        $this->actingAsAdmin();
        Filament::setCurrentPanel('admin');

        $inside = $this->makeApplication('2025-03-10 09:00:00');
        $outside = $this->makeApplication('2025-06-10 09:00:00');

        Livewire::test(AdminListKimApplications::class)
            ->filterTable('submitted_at', ['submitted_from' => '2025-05-01'])
            ->assertCanSeeTableRecords([$outside])
            ->assertCanNotSeeTableRecords([$inside]);
    }

    public function test_admin_findings_filter_by_created_date_range(): void
    {
        $this->actingAsAdmin();
        Filament::setCurrentPanel('admin');

        $application = $this->makeApplication('2025-03-10 09:00:00');
        $inside = $this->makeFinding($application, '2025-03-15 10:00:00');
        $outside = $this->makeFinding($application, '2025-07-15 10:00:00');

        Livewire::test(AdminListFindings::class)
            ->filterTable('created_at', [
                'created_from' => '2025-03-01',
                'created_until' => '2025-03-31',
            ])
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$outside]);
    }

    public function test_transportir_findings_filter_by_created_date_range(): void
    {
        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->owner);

        $application = $this->makeApplication('2025-03-10 09:00:00');
        $inside = $this->makeFinding($application, '2025-03-15 10:00:00');
        $outside = $this->makeFinding($application, '2025-07-15 10:00:00');

        Livewire::test(TransportirListFindings::class)
            ->filterTable('created_at', [
                'created_from' => '2025-03-01',
                'created_until' => '2025-03-31',
            ])
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$outside]);
    }
}
