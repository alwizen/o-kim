<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimCardImageService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unduh gambar kartu KIM seukuran kartu ATM (ISO/IEC 7810 ID-1).
 */
class KimCardImageTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private KimApplication $application;

    private User $owner;

    private array $officers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($transporter);

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role === 'ftm' ? ['ftm'] : [$role, 'pengawas_'.$role]);
            $this->officers[$role] = $officer;
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);

        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->officers['ftm']->id,
        ]);

        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', $department->value))->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $this->application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $this->officers[$department->value],
                );
            }
        }

        $approval = app(ApprovalService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $approval->decide(
                $this->application->fresh(),
                $department,
                ApprovalDecision::Approved,
                $this->officers[$department->value],
            );
        }

        $approval->decide(
            $this->application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->officers['ftm'],
        );
    }

    private function issueCard(): KimCard
    {
        return app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    public function test_transporter_owner_can_download_card_image(): void
    {
        $card = $this->issueCard();

        $response = $this->actingAs($this->owner)
            ->get(route('kim.download-image', $card));

        $response->assertSuccessful();
        $this->assertSame('image/png', $response->headers->get('content-type'));
    }

    public function test_user_with_view_kim_cards_can_download_card_image(): void
    {
        $card = $this->issueCard();

        $ftm = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $ftm->assignRole('ftm');

        $this->assertTrue($ftm->can('view_kim_cards'));

        $this->actingAs($ftm)
            ->get(route('kim.download-image', $card))
            ->assertSuccessful();
    }

    public function test_other_transporter_cannot_download_card_image(): void
    {
        $card = $this->issueCard();

        $otherTransporter = Transporter::factory()->create();
        $intruder = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $intruder->assignRole('transportir');
        $intruder->transporters()->attach($otherTransporter);

        $this->actingAs($intruder)
            ->get(route('kim.download-image', $card))
            ->assertForbidden();
    }

    public function test_tad_officer_without_view_kim_cards_is_forbidden(): void
    {
        $card = $this->issueCard();

        $tad = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $tad->assignRole('hsse');

        $this->assertFalse($tad->can('view_kim_cards'));

        $this->actingAs($tad)
            ->get(route('kim.download-image', $card))
            ->assertForbidden();
    }

    public function test_generated_image_has_atm_card_dimensions(): void
    {
        $card = $this->issueCard();

        $path = app(KimCardImageService::class)->generate($card);

        Storage::disk('local')->assertExists($path);

        $size = getimagesizefromstring(Storage::disk('local')->get($path));

        $this->assertNotFalse($size);
        $this->assertSame(KimCardImageService::WIDTH, $size[0]);
        $this->assertSame(KimCardImageService::HEIGHT, $size[1]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function test_generated_image_path_is_safe_and_persisted(): void
    {
        $card = $this->issueCard();

        $path = app(KimCardImageService::class)->generate($card);

        $this->assertStringStartsWith('kim-cards/', $path);
        $this->assertSame(1, substr_count($path, '/'), 'Path hanya boleh punya satu pemisah direktori.');
        $this->assertStringEndsWith('.png', $path);
        $this->assertSame($path, $card->fresh()->image_path);
    }

    public function test_download_reuses_cached_image(): void
    {
        $card = $this->issueCard();
        $service = app(KimCardImageService::class);

        $service->generate($card);
        $firstPath = $card->fresh()->image_path;
        $firstContents = Storage::disk('local')->get($firstPath);

        $this->actingAs($this->owner)
            ->get(route('kim.download-image', $card))
            ->assertSuccessful();

        $this->assertSame($firstPath, $card->fresh()->image_path);
        $this->assertSame($firstContents, Storage::disk('local')->get($firstPath));
    }
}
