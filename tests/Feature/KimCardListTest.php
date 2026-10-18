<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimCards\KimCardResource;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KimCardListTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private KimApplication $application;

    private array $officers = [];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $transporter = Transporter::factory()->create(['company_name' => 'PT Angkutan Maju']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($transporter);
        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $transporter->id,
            'registration_number' => 'G 1234 TGL',
        ]);

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role);
            $this->officers[$role] = $officer;
        }

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument(
                $this->vehicle,
                $code,
                now()->addMonths(4)->toDateString(),
            );
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);

        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->officers['ftm']->id,
        ]);
    }

    private function issueCard(): KimCard
    {
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

        return app(KimIssuanceService::class)->issue($this->application->fresh());
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_with_permission_can_open_kim_cards_list(): void
    {
        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin/kim-cards')
            ->assertSuccessful();
    }

    public function test_ftm_can_open_kim_cards_list(): void
    {
        $this->actingAs($this->makeUser('ftm'))
            ->get('/admin/kim-cards')
            ->assertSuccessful();
    }

    public function test_hsse_cannot_open_kim_cards_list(): void
    {
        $this->actingAs($this->makeUser('hsse'))
            ->get('/admin/kim-cards')
            ->assertForbidden();
    }

    public function test_issued_card_appears_in_list(): void
    {
        $card = $this->issueCard();

        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin/kim-cards')
            ->assertSuccessful()
            ->assertSee($card->kim_number)
            ->assertSee('G 1234 TGL')
            ->assertSee('PT Angkutan Maju');
    }

    public function test_admin_can_open_kim_card_detail(): void
    {
        $card = $this->issueCard();

        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin/kim-cards/'.$card->getRouteKey())
            ->assertSuccessful()
            ->assertSee($card->kim_number)
            ->assertSee($card->qr_token ? route('kim.validate', $card->qr_token) : '');
    }

    public function test_transportir_cannot_open_admin_kim_cards(): void
    {
        $this->issueCard();

        $this->actingAs($this->owner)
            ->get('/admin/kim-cards')
            ->assertForbidden();
    }

    public function test_resource_respects_view_kim_cards_permission(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(KimCardResource::canAccess());

        auth()->logout();
        $this->actingAs($this->makeUser('hsse'));
        $this->assertFalse(KimCardResource::canAccess());

        auth()->logout();
        $this->actingAs($this->makeUser('ftm'));
        $this->assertTrue(KimCardResource::canAccess());
    }

    public function test_list_shows_expired_status(): void
    {
        $card = $this->issueCard();
        $card->update(['expires_at' => now()->subDay()->toDateString()]);

        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin/kim-cards')
            ->assertSuccessful()
            ->assertSee('Kedaluwarsa');
    }

    public function test_application_without_card_does_not_appear(): void
    {
        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin/kim-cards')
            ->assertSuccessful()
            ->assertDontSee($this->application->application_number);
    }
}
