<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Filament\Transportir\Widgets\VehicleProgressStepper;
use App\Filament\Widgets\ApplicationProgressStepper;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\VehicleProgressService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProgressStepperTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Filament::setCurrentPanel('admin');

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT Stepper Admin']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 2020 STP',
        ]);
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->givePermissionTo($permission);

        return $user;
    }

    private function makeApplication(ApplicationStatus $status): KimApplication
    {
        return KimApplication::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'transporter_id' => $this->transporter->id,
            'status' => $status,
        ]);
    }

    public function test_widget_visible_only_with_permission(): void
    {
        $this->actingAs($this->userWithPermission('view_progress_stepper'));
        $this->assertTrue(ApplicationProgressStepper::canView());

        auth()->logout();
        $this->actingAs($this->userWithPermission('view_applications'));
        $this->assertFalse(
            ApplicationProgressStepper::canView(),
            'Tanpa view_progress_stepper, stepper tidak boleh tampil.',
        );
    }

    public function test_super_admin_can_see_widget_by_default(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $this->assertTrue(ApplicationProgressStepper::canView());
    }

    public function test_widget_lists_applications_across_transporters(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);

        $this->actingAs($this->userWithPermission('view_progress_stepper'));

        $options = Livewire::test(ApplicationProgressStepper::class)
            ->instance()
            ->getApplicationOptions();

        $this->assertArrayHasKey($application->id, $options);
        $this->assertStringContainsString('PT Stepper Admin', $options[$application->id]);
    }

    public function test_widget_excludes_issued_and_cancelled_applications(): void
    {
        $running = $this->makeApplication(ApplicationStatus::Inspection);
        $issued = $this->makeApplication(ApplicationStatus::Issued);
        $cancelled = $this->makeApplication(ApplicationStatus::Cancelled);

        $this->actingAs($this->userWithPermission('view_progress_stepper'));

        $options = Livewire::test(ApplicationProgressStepper::class)
            ->instance()
            ->getApplicationOptions();

        $this->assertArrayHasKey($running->id, $options);
        $this->assertArrayNotHasKey($issued->id, $options);
        $this->assertArrayNotHasKey($cancelled->id, $options);
    }

    public function test_widget_renders_steps_and_links_to_admin_panel(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);

        $this->actingAs($this->userWithPermission('view_progress_stepper'));

        Livewire::test(ApplicationProgressStepper::class)
            ->assertSet('applicationId', $application->id)
            ->assertSee('Progres Pengajuan KIM')
            ->assertSee('Pendaftaran Kendaraan')
            ->assertSee('Verifikasi Dokumen')
            ->assertSee('Pemeriksaan & Temuan')
            ->assertSee('FTM')
            ->assertSee('/admin/kim-applications/'.$application->getRouteKey());
    }

    public function test_service_builds_admin_urls(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);

        $steps = app(VehicleProgressService::class)->stepsFor($application->fresh(), 'admin');
        $vehicleStep = collect($steps)->firstWhere('key', 'vehicle');
        $inspectionStep = collect($steps)->firstWhere('key', 'inspection');

        $this->assertSame('/admin/vehicles/'.$this->vehicle->id, $vehicleStep['url']);
        $this->assertStringStartsWith('/admin/kim-applications/', $inspectionStep['url']);
    }

    public function test_transportir_widget_excludes_issued_applications(): void
    {
        $running = $this->makeApplication(ApplicationStatus::Inspection);
        $issued = $this->makeApplication(ApplicationStatus::Issued);

        $this->actingAs($this->owner);
        Filament::setCurrentPanel('transportir');

        $options = Livewire::test(VehicleProgressStepper::class)
            ->instance()
            ->getApplicationOptions();

        $this->assertArrayHasKey($running->id, $options);
        $this->assertArrayNotHasKey($issued->id, $options);
    }

    public function test_transportir_service_urls_stay_in_transportir_panel(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Inspection);

        $steps = app(VehicleProgressService::class)->stepsFor($application->fresh(), 'transportir');

        $this->assertStringStartsWith('/transportir/', collect($steps)->firstWhere('key', 'vehicle')['url']);
        $this->assertStringStartsWith('/transportir/', collect($steps)->firstWhere('key', 'inspection')['url']);
    }
}
