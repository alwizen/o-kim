<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Filament\Resources\KimApplications\Pages\ListKimApplications;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function test_inspection_result_rejected_before_scheduling(): void
    {
        $this->expectException(ValidationException::class);

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Compliant,
            $this->hsse,
        );
    }

    public function test_inspection_result_allowed_after_scheduling(): void
    {
        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
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

    public function test_inspect_page_forbidden_before_scheduling(): void
    {
        $this->actingAs($this->hsse)
            ->get('/admin/kim-applications/'.$this->application->id.'/inspect')
            ->assertForbidden();
    }

    public function test_inspect_page_accessible_after_scheduling(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->subDay()]);

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
            ->mountTableAction('schedule', $this->application)
            ->set('mountedActions.0.data.inspection_scheduled_at', now()->addDays(3)->format('Y-m-d H:i:s'))
            ->callMountedTableAction();

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

    public function test_schedule_action_label_is_set_tanggal_when_not_scheduled(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHasLabel('schedule', 'Tetapkan Tanggal Pemeriksaan', $this->application->fresh());
    }

    public function test_schedule_action_label_becomes_reschedule_when_scheduled(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHasLabel('schedule', 'Reschedule', $this->application->fresh());
    }

    public function test_reschedule_updates_existing_schedule(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $newDate = now()->addDays(7)->format('Y-m-d H:i:s');

        Livewire::test(ListKimApplications::class)
            ->mountTableAction('schedule', $this->application->fresh())
            ->set('mountedActions.0.data.inspection_scheduled_at', $newDate)
            ->callMountedTableAction();

        $this->assertSame(
            Carbon::parse($newDate)->format('Y-m-d H:i'),
            $this->application->fresh()->inspection_scheduled_at->format('Y-m-d H:i')
        );
    }

    public function test_inspect_action_visible_in_table_for_inspection_officer(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->subDay()]);

        $this->actingAs($this->hsse);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionVisible('inspect', $this->application->fresh());
    }

    public function test_inspect_action_hidden_in_table_for_non_inspector(): void
    {
        // FTM punya akses melihat pengajuan, tetapi tidak berwenang mengisi checklist.
        $ftm = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $ftm->assignRole('ftm');

        $this->actingAs($ftm);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHidden('inspect', $this->application->fresh());
    }

    public function test_inspect_action_hidden_in_table_when_schedule_not_set(): void
    {
        $this->actingAs($this->hsse);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHidden('inspect', $this->application->fresh());
    }

    public function test_inspect_action_hidden_on_view_page_when_schedule_not_set(): void
    {
        $this->actingAs($this->hsse);

        Livewire::test(ViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])->assertActionHidden('inspect');
    }

    public function test_inspect_action_visible_on_view_page_when_schedule_set(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->subDay()]);

        $this->actingAs($this->hsse);

        Livewire::test(ViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])->assertActionVisible('inspect');
    }

    public function test_view_page_schedule_action_label_is_set_tanggal_when_not_scheduled(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(ViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])->assertActionHasLabel('schedule', 'Tetapkan Tanggal Pemeriksaan');
    }

    public function test_view_page_schedule_action_label_becomes_reschedule_when_scheduled(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(ViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])->assertActionHasLabel('schedule', 'Reschedule Pemeriksaan');
    }

    public function test_inspect_action_hidden_in_table_when_schedule_in_future(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $this->actingAs($this->hsse);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHidden('inspect', $this->application->fresh());
    }

    public function test_inspect_action_hidden_on_view_page_when_schedule_in_future(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $this->actingAs($this->hsse);

        Livewire::test(ViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])->assertActionHidden('inspect');
    }

    public function test_inspect_page_forbidden_when_schedule_in_future(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $this->actingAs($this->hsse)
            ->get('/admin/kim-applications/'.$this->application->id.'/inspect')
            ->assertForbidden();
    }

    public function test_inspection_result_rejected_when_schedule_in_future(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->addDay()]);

        $this->expectException(ValidationException::class);

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Compliant,
            $this->hsse,
        );
    }

    public function test_inspect_action_visible_when_schedule_has_arrived(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->subMinute()]);

        $this->actingAs($this->hsse);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionVisible('inspect', $this->application->fresh());
    }

    public function test_inspect_action_url_points_to_inspect_page(): void
    {
        $this->application->update(['inspection_scheduled_at' => now()->subDay()]);

        $this->actingAs($this->hsse);

        Livewire::test(ListKimApplications::class)
            ->assertTableActionHasUrl(
                'inspect',
                KimApplicationResource::getUrl('inspect', [
                    'record' => $this->application,
                ]),
                $this->application->fresh()
            );
    }
}
