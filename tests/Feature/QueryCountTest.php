<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\Findings\FindingResource as AdminFindingResource;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\DocumentType;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Application\KimApplicationService;
use App\Services\Application\VehicleProgressService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi N+1: query tidak boleh tumbuh linear terhadap jumlah baris.
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $hsse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');
    }

    private function makeApplicationWithDocuments(int $documents = 5): KimApplication
    {
        $vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        $type = DocumentType::where('is_required', true)->firstOrFail();

        for ($i = 0; $i < $documents; $i++) {
            VehicleDocument::factory()->create([
                'vehicle_id' => $vehicle->id,
                'document_type_id' => $type->id,
                'verification_status' => DocumentVerificationStatus::Pending,
                'expires_at' => now()->addYear(),
            ]);
        }

        return app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // ---------- PERF-01 ----------

    public function test_kim_application_list_query_does_not_scale_with_rows(): void
    {
        // Buat 8 pengajuan dengan masing-masing 5 dokumen pending.
        for ($i = 0; $i < 8; $i++) {
            $this->makeApplicationWithDocuments();
        }

        $queries = $this->countQueries(function () {
            $applications = KimApplicationResource::getEloquentQuery()->get();

            // Akses kolom yang dulu memicu query per baris.
            $applications->each(fn (KimApplication $application) => $application->vehicle?->pending_documents_count);
        });

        // 1 query aplikasi + 1 query kendaraan (dengan withCount).
        $this->assertLessThanOrEqual(3, $queries, "Query tumbuh linear: {$queries} query");
    }

    // ---------- PERF-02 ----------

    public function test_inspection_form_preloads_existing_results_once(): void
    {
        $application = $this->makeApplicationWithDocuments();
        $application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subHour(),
        ]);

        $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('is_active', true)
            ->get();

        $inspection = app(InspectionService::class);

        foreach ($items as $item) {
            $inspection->recordResult($application->fresh(), $item, InspectionResultValue::Compliant, $this->hsse);
        }

        $itemIds = $items->pluck('id')->all();

        $queries = $this->countQueries(function () use ($application, $itemIds) {
            $results = $application->inspectionResults()
                ->with(['attachments', 'pointResults', 'finding'])
                ->get()
                ->keyBy('inspection_item_id');

            foreach ($itemIds as $itemId) {
                $existing = $results->get($itemId);
                $existing?->attachments->isNotEmpty();
                $existing?->checkedPointMap();
            }
        });

        // 1 query hasil + 1 query attachments + 1 query points + 1 query butir = 4.
        $this->assertLessThanOrEqual(6, $queries, "Preload hasil inspeksi tidak efektif: {$queries} query");
    }

    // ---------- PERF-04 ----------

    public function test_department_completion_map_uses_constant_queries(): void
    {
        $application = $this->makeApplicationWithDocuments();
        $application->update(['inspection_scheduled_at' => now()->subHour()]);

        $queries = $this->countQueries(function () use ($application) {
            app(VehicleProgressService::class)->departmentCompletionMap($application);
        });

        // 1 query butir wajib (+ checklist) + 1 query hasil inspeksi.
        $this->assertLessThanOrEqual(4, $queries, "Peta penyelesaian departemen memicu {$queries} query");
    }

    // ---------- PERF-07 ----------

    public function test_finding_list_eager_loads_nested_relations(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $application = $this->makeApplicationWithDocuments(1);

            Finding::create([
                'kim_application_id' => $application->id,
                'department' => Department::Hsse,
                'description' => 'Temuan '.$i,
                'status' => FindingStatus::Open,
                'created_by' => $this->hsse->id,
            ]);
        }

        $queries = $this->countQueries(function () {
            $findings = AdminFindingResource::getEloquentQuery()->get();

            $findings->each(function (Finding $finding) {
                $finding->application?->transporter?->company_name;
                $finding->inspectionResult?->item?->label;
            });
        });

        // 1 query temuan + 1 aplikasi + 1 transporter + 1 hasil + 1 butir.
        $this->assertLessThanOrEqual(6, $queries, "Relasi bersarang temuan memicu {$queries} query");
    }
}
