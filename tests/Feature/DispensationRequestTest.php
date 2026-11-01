<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\Department;
use App\Enums\DispensationRequestStatus;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\DispensationRequests\DispensationRequestResource;
use App\Filament\Resources\DispensationRequests\Pages\ListDispensationRequests;
use App\Filament\Transportir\Resources\Findings\Pages\ViewFinding;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DispensationRequestApproved;
use App\Notifications\DispensationRequestRejected;
use App\Notifications\DispensationRequestSubmitted;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\DispensationService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alur dispensasi: transportir mengajukan permohonan, FTM memutuskan.
 */
class DispensationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $ftm;

    private Vehicle $vehicle;

    private KimApplication $application;

    /** @var array<string, User> */
    private array $officers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');
        Notification::fake();

        $this->transporter = Transporter::factory()->create();
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role === 'ftm' ? ['ftm'] : [$role, 'pengawas_'.$role]);
            $this->officers[$role] = $officer;
        }

        $this->ftm = $this->officers['ftm'];

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);
        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->ftm->id,
        ]);
    }

    private function finding(): Finding
    {
        $inspection = app(InspectionService::class);
        $finding = null;

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', $department->value))
                ->where('is_active', true)
                ->get();

            foreach ($items as $index => $item) {
                $isTarget = $finding === null && $department === Department::Hsse && $index === 0;

                $inspection->recordResult(
                    $this->application->fresh(),
                    $item,
                    $isTarget ? InspectionResultValue::Finding : InspectionResultValue::Compliant,
                    $this->officers[$department->value],
                    findingDescription: $isTarget ? 'Sparepart pompa harus di-inden' : null,
                );

                if ($isTarget) {
                    $finding = $this->application->fresh()->findings()->latest('id')->firstOrFail();
                }
            }
        }

        return $finding;
    }

    private function service(): DispensationService
    {
        return app(DispensationService::class);
    }

    private function tomorrow(): Carbon
    {
        return Carbon::today()->addDay();
    }

    // ---------- Pengajuan oleh transportir ----------

    public function test_transportir_can_request_dispensation(): void
    {
        $finding = $this->finding();

        $request = $this->service()->request(
            $finding,
            $this->owner,
            'Menunggu suku cadang impor, estimasi tiba 2 minggu.',
        );

        $this->assertSame(DispensationRequestStatus::Pending, $request->status);
        $this->assertSame($this->owner->id, $request->requested_by);
        $this->assertDatabaseHas('finding_dispensation_requests', [
            'finding_id' => $finding->id,
            'status' => 'pending',
        ]);
    }

    public function test_request_sends_notification_to_ftm(): void
    {
        $finding = $this->finding();

        $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        Notification::assertSentTo($this->ftm, DispensationRequestSubmitted::class);
    }

    public function test_other_transporter_cannot_request_dispensation(): void
    {
        $finding = $this->finding();

        $other = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $other->assignRole('transportir');
        $other->transporters()->attach(Transporter::factory()->create());

        $this->expectException(ValidationException::class);

        $this->service()->request($finding, $other, 'Menunggu suku cadang impor.');
    }

    public function test_cannot_request_twice_while_pending(): void
    {
        $finding = $this->finding();

        $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $this->expectException(ValidationException::class);

        $this->service()->request($finding->fresh(), $this->owner, 'Permohonan kedua.');
    }

    public function test_reason_is_required_and_minimum_length(): void
    {
        $finding = $this->finding();

        $this->expectException(ValidationException::class);

        $this->service()->request($finding, $this->owner, 'pendek');
    }

    public function test_finding_stays_open_while_waiting_decision(): void
    {
        $finding = $this->finding();

        $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $this->assertSame(FindingStatus::Open, $finding->fresh()->status);
        $this->assertTrue($finding->fresh()->blocksApproval());
    }

    // ---------- Keputusan FTM ----------

    public function test_ftm_can_approve_request_and_sets_due_date(): void
    {
        $finding = $this->finding();
        $request = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $decided = $this->service()->approveRequest($request, $this->ftm, $this->tomorrow());

        $this->assertSame(DispensationRequestStatus::Approved, $decided->status);
        $this->assertSame($this->ftm->id, $decided->decided_by);
        $this->assertNotNull($decided->due_date);

        $finding->refresh();
        $this->assertSame(FindingStatus::Dispensation, $finding->status);
        $this->assertSame($this->tomorrow()->toDateString(), $finding->dispensation_due_date->toDateString());
        $this->assertFalse($finding->blocksApproval());
    }

    public function test_approval_notifies_transporter(): void
    {
        $finding = $this->finding();
        $request = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $this->service()->approveRequest($request, $this->ftm, $this->tomorrow());

        Notification::assertSentTo($this->owner, DispensationRequestApproved::class);
    }

    public function test_ftm_can_reject_request_and_finding_stays_open(): void
    {
        $finding = $this->finding();
        $request = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $decided = $this->service()->rejectRequest($request, $this->ftm, 'Perbaikan dapat dilakukan minggu ini.');

        $this->assertSame(DispensationRequestStatus::Rejected, $decided->status);
        $this->assertSame(FindingStatus::Open, $finding->fresh()->status);
        $this->assertTrue($finding->fresh()->blocksApproval());

        Notification::assertSentTo($this->owner, DispensationRequestRejected::class);
    }

    public function test_transportir_may_request_again_after_rejection(): void
    {
        $finding = $this->finding();
        $first = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');
        $this->service()->rejectRequest($first, $this->ftm, 'Perbaikan dapat dilakukan minggu ini.');

        $second = $this->service()->request($finding->fresh(), $this->owner, 'Suku cadang belum tersedia juga.');

        $this->assertSame(DispensationRequestStatus::Pending, $second->status);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_non_ftm_cannot_approve_request(): void
    {
        $finding = $this->finding();
        $request = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $this->expectException(ValidationException::class);

        $this->service()->approveRequest($request, $this->officers['hsse'], $this->tomorrow());
    }

    public function test_due_date_must_be_in_the_future(): void
    {
        $finding = $this->finding();
        $request = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $this->expectException(ValidationException::class);

        $this->service()->approveRequest($request, $this->ftm, Carbon::today());
    }

    public function test_cannot_decide_already_decided_request(): void
    {
        $finding = $this->finding();
        $request = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');
        $this->service()->rejectRequest($request, $this->ftm, 'Perbaikan dapat dilakukan minggu ini.');

        $this->expectException(ValidationException::class);

        $this->service()->approveRequest($request->fresh(), $this->ftm, $this->tomorrow());
    }

    // ---------- UI ----------

    public function test_request_button_visible_for_owner_and_hidden_when_pending(): void
    {
        $finding = $this->finding();

        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->owner);

        Livewire::test(ViewFinding::class, ['record' => $finding->getRouteKey()])
            ->assertActionVisible('request_dispensation');

        $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        Livewire::test(ViewFinding::class, ['record' => $finding->getRouteKey()])
            ->assertActionHidden('request_dispensation');
    }

    public function test_ftm_queue_lists_pending_requests_by_default(): void
    {
        $finding = $this->finding();
        $pending = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->ftm);

        Livewire::test(ListDispensationRequests::class)
            ->assertCanSeeTableRecords([$pending]);
    }

    public function test_decided_requests_are_kept_for_record_when_filter_cleared(): void
    {
        $finding = $this->finding();
        $pending = $this->service()->request($finding, $this->owner, 'Menunggu suku cadang impor.');

        $this->service()->rejectRequest($pending, $this->ftm, 'Perbaikan dapat dilakukan minggu ini.');

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->ftm);

        // Riwayat tetap tersimpan dan tersedia untuk laporan: query resource tidak
        // lagi membatasi ke status pending.
        $this->assertTrue(
            DispensationRequestResource::getEloquentQuery()->pluck('id')->contains($pending->id),
            'Permohonan yang sudah diputuskan harus tetap tersedia sebagai rekaman.',
        );
    }
}
