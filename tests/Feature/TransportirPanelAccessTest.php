<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportirPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function transportirWithVehicle(): array
    {
        $transporter = Transporter::factory()->create();
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');
        $user->transporters()->attach($transporter);
        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        return [$user, $transporter, $vehicle];
    }

    public function test_transportir_can_view_own_application(): void
    {
        [$user, $transporter, $vehicle] = $this->transportirWithVehicle();
        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);

        $this->actingAs($user)
            ->get('/transportir/kim-applications/'.$application->getRouteKey())
            ->assertSuccessful();
    }

    public function test_transportir_cannot_view_other_company_application(): void
    {
        [, , $vehicle] = $this->transportirWithVehicle();
        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);

        [$otherUser] = $this->transportirWithVehicle();

        $this->actingAs($otherUser)
            ->get('/transportir/kim-applications/'.$application->getRouteKey())
            ->assertNotFound();
    }

    public function test_transportir_can_view_own_finding(): void
    {
        [$user, $transporter, $vehicle] = $this->transportirWithVehicle();
        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);

        $finding = Finding::create([
            'kim_application_id' => $application->id,
            'department' => Department::Hsse,
            'description' => 'Temuan uji',
            'status' => FindingStatus::Open,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/transportir/findings/'.$finding->getRouteKey())
            ->assertSuccessful();
    }

    public function test_transportir_panel_resources_require_transportir_role(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        // Super admin tidak bisa mengakses resource panel transportir.
        $this->actingAs($admin)->get('/transportir/kim-applications')->assertForbidden();
        $this->actingAs($admin)->get('/transportir/vehicles')->assertForbidden();
        $this->actingAs($admin)->get('/transportir/findings')->assertForbidden();
    }
}
