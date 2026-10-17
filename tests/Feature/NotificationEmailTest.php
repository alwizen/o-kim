<?php

namespace Tests\Feature;

use App\Actions\Document\RejectVehicleDocument;
use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Enums\InspectionResultValue;
use App\Jobs\SendNotificationMail;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationApprovedByDepartment;
use App\Notifications\ApplicationPendingFinalApproval;
use App\Notifications\ApplicationRejected;
use App\Notifications\FindingCreated;
use App\Notifications\KimApplicationSubmitted;
use App\Notifications\KimCardIssued;
use App\Notifications\TransporterRegistered;
use App\Notifications\TransporterRegistrationSubmitted;
use App\Notifications\VehicleDocumentRejected;
use App\Notifications\VehicleDocumentVerified;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $verifier;

    private User $ftm;

    private array $officers = [];

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT Uji Notifikasi']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->verifier = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->verifier->assignRole('admin_verifikasi');

        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $officer = User::factory()->create(['account_status' => AccountStatus::Approved]);
            $officer->assignRole($role);
            $this->officers[$role] = $officer;
        }

        $this->ftm = $this->officers['ftm'];

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 1234 NTF',
        ]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }
    }

    private function submittedApplication(): KimApplication
    {
        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        app(KimApplicationService::class)->submit($application, $this->owner);

        return $application->fresh();
    }

    private function completeInspections(KimApplication $application): void
    {
        $inspection = app(InspectionService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $items = InspectionItem::whereHas(
                'checklist',
                fn ($query) => $query->where('department', $department->value)
            )->get();

            foreach ($items as $item) {
                $inspection->recordResult(
                    $application->fresh(),
                    $item,
                    InspectionResultValue::Compliant,
                    $this->officers[$department->value],
                );
            }
        }
    }

    private function approveAllFunctional(KimApplication $application): void
    {
        $approval = app(ApprovalService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $approval->decide(
                $application->fresh(),
                $department,
                ApprovalDecision::Approved,
                $this->officers[$department->value],
            );
        }
    }

    private function readyForIssuance(): KimApplication
    {
        $application = $this->submittedApplication();
        $this->completeInspections($application);
        $this->approveAllFunctional($application);

        app(ApprovalService::class)->decide(
            $application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        return $application->fresh();
    }

    // ---------- Registrasi ----------

    public function test_registration_notifies_owner_and_verifier_once(): void
    {
        Notification::fake();

        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $payload = [
            'pic_name' => 'Budi Santoso',
            'company_name' => 'PT Contoh',
            'company_address' => 'Jl. Contoh 1',
            'phone' => '0283-111',
        ];

        $this->actingAs($user)->post(route('registration.profile.update'), $payload);

        Notification::assertSentTo($user, TransporterRegistered::class);
        Notification::assertSentTo($this->verifier, TransporterRegistrationSubmitted::class);

        // Simpan ulang profil tidak boleh mengirim notifikasi baru.
        $this->actingAs($user)->post(route('registration.profile.update'), $payload);

        Notification::assertSentToTimes($user, TransporterRegistered::class, 1);
        Notification::assertSentToTimes($this->verifier, TransporterRegistrationSubmitted::class, 1);
    }

    // ---------- Pengajuan ----------

    public function test_application_submission_notifies_reviewers(): void
    {
        Notification::fake();

        $application = $this->submittedApplication();

        Notification::assertSentTo(
            $this->officers['hsse'],
            KimApplicationSubmitted::class,
            fn (KimApplicationSubmitted $notification) => $notification->applicationNumber === $application->application_number,
        );
    }

    // ---------- Temuan ----------

    public function test_finding_creation_notifies_transporter(): void
    {
        Notification::fake();

        $application = $this->submittedApplication();
        $item = InspectionItem::whereHas(
            'checklist',
            fn ($query) => $query->where('department', 'hsse')
        )->firstOrFail();

        app(InspectionService::class)->recordResult(
            $application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->officers['hsse'],
            findingDescription: 'APAR kedaluwarsa',
        );

        Notification::assertSentTo($this->owner, FindingCreated::class);
    }

    // ---------- Approval ----------

    public function test_functional_approval_notifies_transporter(): void
    {
        Notification::fake();

        $application = $this->submittedApplication();
        $this->completeInspections($application);

        app(ApprovalService::class)->decide(
            $application->fresh(),
            Department::Hsse,
            ApprovalDecision::Approved,
            $this->officers['hsse'],
        );

        Notification::assertSentTo($this->owner, ApplicationApprovedByDepartment::class);
    }

    public function test_final_approval_request_notifies_ftm(): void
    {
        Notification::fake();

        $application = $this->submittedApplication();
        $this->completeInspections($application);
        $this->approveAllFunctional($application);

        Notification::assertSentTo($this->ftm, ApplicationPendingFinalApproval::class);
    }

    public function test_final_approval_notifies_transporter(): void
    {
        Notification::fake();

        $application = $this->submittedApplication();
        $this->completeInspections($application);
        $this->approveAllFunctional($application);

        app(ApprovalService::class)->decide(
            $application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->ftm,
        );

        Notification::assertSentTo($this->owner, ApplicationApproved::class);
    }

    public function test_rejection_notifies_transporter(): void
    {
        Notification::fake();

        $application = $this->submittedApplication();
        $this->completeInspections($application);

        app(ApprovalService::class)->decide(
            $application->fresh(),
            Department::Hsse,
            ApprovalDecision::Rejected,
            $this->officers['hsse'],
            'Tidak memenuhi syarat',
        );

        Notification::assertSentTo($this->owner, ApplicationRejected::class);
    }

    // ---------- KIM ----------

    public function test_kim_issuance_notifies_transporter(): void
    {
        Notification::fake();

        $application = $this->readyForIssuance();
        $card = app(KimIssuanceService::class)->issue($application);

        Notification::assertSentTo(
            $this->owner,
            KimCardIssued::class,
            fn (KimCardIssued $notification) => $notification->kimNumber === $card->kim_number,
        );
    }

    // ---------- Dokumen ----------

    public function test_document_verification_notifies_transporter(): void
    {
        Notification::fake();

        $document = VehicleDocument::where('vehicle_id', $this->vehicle->id)->firstOrFail();

        app(VerifyVehicleDocument::class)->execute($document, $this->verifier);

        Notification::assertSentTo($this->owner, VehicleDocumentVerified::class);
    }

    public function test_document_rejection_notifies_transporter(): void
    {
        Notification::fake();

        $document = VehicleDocument::where('vehicle_id', $this->vehicle->id)->firstOrFail();

        app(RejectVehicleDocument::class)->execute($document, $this->verifier, 'Foto buram');

        Notification::assertSentTo($this->owner, VehicleDocumentRejected::class);
    }

    // ---------- Perilaku teknis ----------

    public function test_email_is_dispatched_to_queue(): void
    {
        Queue::fake();

        $this->owner->notify(new TransporterRegistered);

        Queue::assertPushed(SendNotificationMail::class);
    }

    public function test_database_payload_is_filament_compatible(): void
    {
        // Tidak memakai Notification::fake agar channel database benar-benar menulis.
        Queue::fake();

        $this->owner->notify(new TransporterRegistered);

        $notification = $this->owner->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame('Pendaftaran Diterima', $notification->data['title']);
    }

    public function test_email_failure_does_not_break_business_flow(): void
    {
        Queue::fake();

        // Notifikasi tetap dikirim (masuk antrean) walau nanti pengiriman email gagal.
        $document = VehicleDocument::where('vehicle_id', $this->vehicle->id)->firstOrFail();

        app(VerifyVehicleDocument::class)->execute($document, $this->verifier);

        $this->assertSame(
            DocumentVerificationStatus::Verified,
            $document->fresh()->verification_status,
        );

        Queue::assertPushed(SendNotificationMail::class);
    }
}
