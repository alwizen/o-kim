<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\InspectKimApplication;
use App\Models\DocumentType;
use App\Models\InspectionItem;
use App\Models\InspectionItemPoint;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Document\VehicleDocumentService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionPointsTest extends TestCase
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

        $docService = app(VehicleDocumentService::class);
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $docService->upload(
                $this->vehicle,
                DocumentType::where('code', $code)->value('id'),
                UploadedFile::fake()->create("{$code}.pdf", 100, 'application/pdf'),
                ['expires_at' => now()->addYear()->toDateString()]
            );
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->transportir);
    }

    private function hsseItem(): InspectionItem
    {
        return InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('item_code', 'HSSE-01')->firstOrFail();
    }

    public function test_seeded_item_has_points(): void
    {
        $this->assertGreaterThanOrEqual(3, $this->hsseItem()->points()->count());
    }

    public function test_service_stores_checked_points(): void
    {
        $item = $this->hsseItem();
        $points = $item->points()->pluck('id')->all();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
            pointIds: [$points[0], $points[1]],
        );

        $this->assertDatabaseHas('inspection_result_points', [
            'inspection_item_point_id' => $points[0],
            'is_checked' => true,
        ]);
        $this->assertDatabaseHas('inspection_result_points', [
            'inspection_item_point_id' => $points[1],
            'is_checked' => true,
        ]);
        // Sub-butir yang tidak dipilih tetap tercatat sebagai tidak dicentang.
        $this->assertDatabaseHas('inspection_result_points', [
            'inspection_item_point_id' => $points[2],
            'is_checked' => false,
        ]);
    }

    public function test_service_updates_points_without_duplicates(): void
    {
        $item = $this->hsseItem();
        $points = $item->points()->pluck('id')->all();
        $service = app(InspectionService::class);

        $service->recordResult($this->application->fresh(), $item, InspectionResultValue::Compliant, $this->hsse, pointIds: [$points[0]]);
        $service->recordResult($this->application->fresh(), $item, InspectionResultValue::Compliant, $this->hsse, pointIds: [$points[1]]);

        $result = $this->application->inspectionResults()->where('inspection_item_id', $item->id)->first();

        $this->assertSame(1, $result->pointResults()->where('inspection_item_point_id', $points[0])->count());
        $this->assertFalse($result->checkedPointMap()[$points[0]]);
        $this->assertTrue($result->checkedPointMap()[$points[1]]);
    }

    public function test_service_ignores_point_ids_from_other_items(): void
    {
        $item = $this->hsseItem();
        $foreignPoint = InspectionItemPoint::where('inspection_item_id', '!=', $item->id)->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
            pointIds: [$foreignPoint->id],
        );

        $result = $this->application->inspectionResults()->where('inspection_item_id', $item->id)->first();

        $this->assertDatabaseMissing('inspection_result_points', [
            'inspection_result_id' => $result->id,
            'inspection_item_point_id' => $foreignPoint->id,
        ]);
    }

    public function test_form_displays_item_points(): void
    {
        $pointLabel = $this->hsseItem()->points()->first()->label;

        $this->actingAs($this->hsse)
            ->get('/admin/kim-applications/'.$this->application->id.'/inspect')
            ->assertSuccessful()
            ->assertSee($pointLabel);
    }

    public function test_form_prefills_checked_points(): void
    {
        $item = $this->hsseItem();
        $points = $item->points()->pluck('id')->all();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
            pointIds: [$points[0]],
        );

        $this->actingAs($this->hsse);

        Livewire::test(InspectKimApplication::class, ['record' => $this->application->fresh()])
            ->assertSet("data.results.{$item->id}.points", [$points[0]]);
    }

    public function test_save_persists_points_via_page(): void
    {
        $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->get();
        $item = $this->hsseItem();
        $points = $item->points()->pluck('id')->all();

        $this->actingAs($this->hsse);

        $component = Livewire::test(InspectKimApplication::class, ['record' => $this->application->fresh()]);

        // Semua butir wajib diisi; sub-butir diisi untuk butir pertama.
        foreach ($items as $each) {
            $component->set("data.results.{$each->id}.result", InspectionResultValue::Compliant->value);
        }

        $component
            ->set("data.results.{$item->id}.points", [$points[0], $points[1]])
            ->call('save')
            ->assertHasNoErrors();

        $result = $this->application->fresh()->inspectionResults()->where('inspection_item_id', $item->id)->first();

        $this->assertNotNull($result);
        $this->assertTrue($result->checkedPointMap()[$points[0]]);
        $this->assertTrue($result->checkedPointMap()[$points[1]]);
    }
}
