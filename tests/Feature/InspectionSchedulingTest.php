<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\ListKimApplications;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $transportir;

    private User $hsse;

    private Vehicle $vehicle;

    private KimApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($transporter);
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->transportir);
    }

    private function hsseItem(): InspectionItem
    {
        return InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();
    }

    public function test_new_submitted_application_has_no_schedule(): void
    {
        $this->assertNull($this->application->fresh()->inspection_scheduled_at);
        $this->assertFalse($this->application->fresh()->isInspectionScheduled());
    }

    public function test_inspection_result_allowed_without_scheduling(): void
    {
        // Jadwal bersifat informasi; pemeriksaan tetap dapat dilakukan tanpa jadwal.
        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Compliant,
            $this->hsse,
        );

        $this->assertDatabaseHas('inspection_results', [
            'kim_application_id' => $this->application->id,
            'result' => 'compliant',
        ]);
    }

    public function test_inspection_result_allowed_after_scheduling(): void
    {
        $this->application->update([
            'inspection_scheduled_at' => now()->addDay(),
            'inspection_scheduled_by' => $this->hsse->id,
        ]);

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Compliant,
            $this->hsse,
        );

        $this->assertDatabaseHas('inspection_results', [
            'kim_application_id' => $this->application->id,
            'result' => 'compliant',
        ]);
    }

    public function test_inspect_page_accessible_without_scheduling(): void
    {
        // Halaman pemeriksaan dapat dibuka walau jadwal belum ditetapkan.
        $this->actingAs($this->hsse)
            ->get('/admin/kim-applications/'.$this->application->id.'/inspect')
            ->assertSuccessful();
    }

    public function test_inspect_page_accessible_after_scheduling(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $this->actingAs($this->hsse)
            ->get('/admin/kim-applications/'.$this->application->id.'/inspect')
            ->assertSuccessful();
    }

    public function test_admin_can_set_schedule_via_table_action(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(ListKimApplications::class)
            ->callTableAction('schedule', $this->application, data: [
                'inspection_scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ])
            ->assertHasNoTableActionErrors();

        $application = $this->application->fresh();

        $this->assertNotNull($application->inspection_scheduled_at);
        $this->assertSame($admin->id, $application->inspection_scheduled_by);
    }

    public function test_schedule_records_scheduler_relation(): void
    {
        $this->application->update([
            'inspection_scheduled_at' => now()->addDay(),
            'inspection_scheduled_by' => $this->hsse->id,
        ]);

        $this->assertSame($this->hsse->id, $this->application->fresh()->scheduler->id);
    }

    public function test_schedule_action_hidden_when_already_issued(): void
    {
        $this->application->update([
            'status' => ApplicationStatus::Issued,
            'inspection_scheduled_at' => now()->addDay(),
        ]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHidden('schedule', $this->application->fresh());
    }
}
