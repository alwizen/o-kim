<?php

namespace Tests\Feature;

use App\Actions\Document\RejectVehicleDocument;
use App\Enums\AccountStatus;
use App\Models\DocumentType;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\VehicleCreated;
use App\Notifications\VehicleDocumentRejected;
use App\Notifications\VehicleDocumentUploaded;
use App\Services\Document\VehicleDocumentService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\NotificationSettingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, NotificationSettingSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake('local');
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::factory()->create([
            'transporter_id' => Transporter::factory()->create(['company_name' => 'PT Uji Logistik'])->id,
        ]);
    }

    public function test_document_upload_notifies_verifiers(): void
    {
        Notification::fake();

        $vehicle = $this->vehicle();
        $admin = $this->staff('admin_verifikasi');

        app(VehicleDocumentService::class)->upload(
            $vehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('stnk.pdf', 100, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );

        Notification::assertSentTo($admin, VehicleDocumentUploaded::class);
    }

    public function test_document_upload_does_not_notify_roles_without_permission(): void
    {
        Notification::fake();

        $vehicle = $this->vehicle();
        $hsse = $this->staff('hsse');

        app(VehicleDocumentService::class)->upload(
            $vehicle,
            DocumentType::where('code', 'STNK')->value('id'),
            UploadedFile::fake()->create('stnk.pdf', 100, 'application/pdf'),
            ['expires_at' => now()->addYear()->toDateString()],
        );

        Notification::assertNotSentTo($hsse, VehicleDocumentUploaded::class);
    }

    public function test_rejected_document_notifies_all_pics(): void
    {
        Notification::fake();

        $transporter = Transporter::factory()->create();
        $owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $owner->assignRole('transportir');
        $owner->transporters()->attach($transporter);

        $secondPic = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $secondPic->assignRole('transportir');
        $secondPic->transporters()->attach($transporter);

        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);
        $document = VehicleDocument::factory()->create([
            'vehicle_id' => $vehicle->id,
            'document_type_id' => DocumentType::where('code', 'STNK')->value('id'),
        ]);

        $admin = $this->staff('admin_verifikasi');

        app(RejectVehicleDocument::class)->execute($document, $admin, 'Buram');

        Notification::assertSentTo([$owner, $secondPic], VehicleDocumentRejected::class);
    }

    public function test_vehicle_created_notification_carries_company_and_nopol(): void
    {
        $vehicle = $this->vehicle();

        $notification = new VehicleCreated('PT Uji Logistik', 'B 1234 XY', '5');
        $admin = $this->staff('ftm');

        $mail = $notification->toMail($admin);

        $this->assertSame('Kendaraan Baru — O-KIM', $mail->subject);
        $this->assertContains('Perusahaan: PT Uji Logistik', $mail->introLines);
        $this->assertContains('NOPOL: B 1234 XY', $mail->introLines);
        $this->assertSame('Kendaraan Baru Ditambahkan', $notification->toDatabase($admin)['title']);
        $this->assertNotNull($vehicle);
    }
}
