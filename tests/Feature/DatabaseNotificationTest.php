<?php

namespace Tests\Feature;

use App\Actions\Document\RejectVehicleDocument;
use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Document\VehicleDocumentService;
use App\Support\AppNotifier;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake('local');
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    private function transportir(): array
    {
        $transporter = Transporter::factory()->create();
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');
        $user->transporters()->attach($transporter);
        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        return [$user, $transporter, $vehicle];
    }

    private function assertHasDatabaseNotification(User $user, string $titlePart): void
    {
        $found = $user->notifications()
            ->get()
            ->contains(fn ($n) => str_contains($n->data['title'] ?? '', $titlePart));

        $this->assertTrue($found, "Notifikasi berjudul mengandung \"{$titlePart}\" tidak ditemukan untuk {$user->email}.");
    }

    // ---------- Helper AppNotifier ----------

    public function test_notifier_stores_database_notification(): void
    {
        $user = $this->staff('hsse');

        AppNotifier::toUser($user, 'Judul Uji', 'Isi uji', 'heroicon-o-bell', 'info');

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('Judul Uji', $user->notifications()->first()->data['title']);
        $this->assertSame('filament', $user->notifications()->first()->data['format']);
    }

    public function test_notifier_targets_staff_with_permission(): void
    {
        $hsse = $this->staff('hsse');
        $ftm = $this->staff('ftm');
        $transportir = $this->staff('admin_verifikasi');

        AppNotifier::toStaffWithPermission('inspect_hsse', 'Untuk HSSE');

        $this->assertHasDatabaseNotification($hsse, 'Untuk HSSE');
        $this->assertSame(0, $ftm->notifications()->count());
        $this->assertSame(0, $transportir->notifications()->count());
    }

    public function test_notifier_targets_all_transporter_pics(): void
    {
        [$owner, $transporter] = $this->transportir();

        AppNotifier::toTransporter($transporter->id, 'Untuk Transportir');

        $this->assertHasDatabaseNotification($owner, 'Untuk Transportir');
    }

    // ---------- Event nyata ----------

    public function test_vehicle_creation_notifies_document_verifiers(): void
    {
        [, , $vehicle] = $this->transportir();
        $ftm = $this->staff('ftm');

        AppNotifier::toStaffWithPermission(
            'verify_documents',
            'Kendaraan Baru Ditambahkan',
            'Kendaraan '.$vehicle->registration_number,
        );

        $this->assertHasDatabaseNotification($ftm, 'Kendaraan Baru');
    }

    public function test_application_submission_notifies_reviewers(): void
    {
        [$owner, , $vehicle] = $this->transportir();
        $admin = $this->makeSuperAdmin();

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($vehicle, $code, verifier: $admin);
        }

        $application = app(KimApplicationService::class)->createDraft($vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($application, $owner);

        $this->assertHasDatabaseNotification($admin, 'Pengajuan KIM Baru');
    }

    public function test_document_verification_notifies_owner(): void
    {
        [$owner, , $vehicle] = $this->transportir();
        $admin = $this->makeSuperAdmin();

        $document = app(VehicleDocumentService::class)->upload(
            $vehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('stnk.pdf', 100, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );

        app(VerifyVehicleDocument::class)->execute($document, $admin);

        $this->assertHasDatabaseNotification($owner, 'Dokumen Kendaraan Diverifikasi');
    }

    public function test_document_rejection_notifies_owner(): void
    {
        [$owner, , $vehicle] = $this->transportir();
        $admin = $this->makeSuperAdmin();

        $document = app(VehicleDocumentService::class)->upload(
            $vehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('stnk.pdf', 100, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );

        app(RejectVehicleDocument::class)->execute($document, $admin, 'Buram');

        $this->assertHasDatabaseNotification($owner, 'Dokumen Kendaraan Ditolak');
    }

    // ---------- Panel ----------

    public function test_admin_panel_has_database_notifications_enabled(): void
    {
        $this->assertTrue(
            Filament::getPanel('admin')->hasDatabaseNotifications()
        );
    }

    public function test_database_notifications_component_lists_stored_notifications(): void
    {
        $admin = $this->makeSuperAdmin();
        AppNotifier::toUser($admin, 'Uji Lonceng');

        $this->actingAs($admin);

        Livewire::test(DatabaseNotifications::class)
            ->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_transportir_panel_has_database_notifications(): void
    {
        $this->assertTrue(
            Filament::getPanel('transportir')->hasDatabaseNotifications()
        );
    }
}
