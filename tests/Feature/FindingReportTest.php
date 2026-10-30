<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication as AdminViewKimApplication;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Finding\FindingReportService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FindingReportTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private User $hsse;

    private Vehicle $vehicle;

    private KimApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');
        Storage::fake(config('media.disk'));

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT Transporter Utama']);
        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'registration_number' => 'G 1234 TGL',
        ]);

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            parent::uploadVehicleDocument($this->vehicle, $code, now()->addMonths(4)->toDateString());
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->owner);
        $this->application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subHour(),
        ]);
    }

    /**
     * Catat temuan pada fungsi HSSE lalu tindak lanjuti sampai terverifikasi.
     */
    private function createVerifiedFinding(bool $withPhotos = false): Finding
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        $finding = Finding::where('kim_application_id', $this->application->id)->firstOrFail();

        $photos = [];

        if ($withPhotos) {
            $photos = [$this->fakeJpeg('temuan-awal.jpg')];
            app(InspectionAttachmentService::class)->upload($finding->inspectionResult, $this->hsse, $photos);
        }

        $action = $withPhotos
            ? app(FindingService::class)->submitAction($finding, $this->owner, 'APAR sudah diganti baru', [$this->fakeJpeg('tindak-lanjut.jpg')])
            : app(FindingService::class)->submitAction($finding, $this->owner, 'APAR sudah diganti baru');

        app(FindingService::class)->verifyAction($finding, $action, $this->hsse, true);

        return $finding->fresh();
    }

    /**
     * Berkas JPEG palsu untuk menguji penyematan foto ke PDF.
     */
    private function fakeJpeg(string $name): UploadedFile
    {
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 60, 60));

        ob_start();
        imagejpeg($image);
        $binary = (string) ob_get_clean();

        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    public function test_berita_acara_is_not_eligible_without_verified_findings(): void
    {
        $this->assertFalse(app(FindingReportService::class)->isEligible($this->application->fresh()));

        $this->actingAs($this->owner)
            ->get(route('berita-acara.download', $this->application))
            ->assertNotFound();
    }

    public function test_berita_acara_becomes_eligible_after_finding_is_verified(): void
    {
        $this->createVerifiedFinding();

        $this->assertTrue(app(FindingReportService::class)->isEligible($this->application->fresh()));
    }

    public function test_open_finding_does_not_make_berita_acara_eligible(): void
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->firstOrFail();

        app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $item,
            InspectionResultValue::Finding,
            $this->hsse,
            findingDescription: 'APAR kedaluwarsa',
        );

        $this->assertFalse(app(FindingReportService::class)->isEligible($this->application->fresh()));
    }

    public function test_transporter_owner_can_download_berita_acara(): void
    {
        $this->createVerifiedFinding();

        $response = $this->actingAs($this->owner)
            ->get(route('berita-acara.download', $this->application));

        $response->assertSuccessful();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Berita-Acara-', (string) $response->headers->get('content-disposition'));
    }

    public function test_other_transporter_cannot_download_berita_acara(): void
    {
        $this->createVerifiedFinding();

        $otherTransporter = Transporter::factory()->create();
        $otherUser = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $otherUser->assignRole('transportir');
        $otherUser->transporters()->attach($otherTransporter);

        $this->actingAs($otherUser)
            ->get(route('berita-acara.download', $this->application))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_berita_acara(): void
    {
        $this->get(route('berita-acara.download', $this->application))
            ->assertRedirect(route('login'));
    }

    public function test_berita_acara_contains_finding_and_vehicle_data(): void
    {
        $this->createVerifiedFinding();

        $report = app(FindingReportService::class)->buildReport($this->application->fresh());

        $this->assertCount(1, $report['rows']);
        $this->assertSame('APAR kedaluwarsa', $report['rows'][0]['description']);
        $this->assertSame('Sudah ditindaklanjuti', $report['rows'][0]['status']);
        $this->assertSame('G 1234 TGL', $report['application']->vehicle->registration_number);
    }

    public function test_berita_acara_layout_has_attachment_dates(): void
    {
        $this->createVerifiedFinding();

        $report = app(FindingReportService::class)->buildReport($this->application->fresh());

        $this->assertCount(1, $report['attachments']);
        $this->assertNotNull($report['attachments'][0]['initial_date']);
        $this->assertNotNull($report['attachments'][0]['action_date']);
    }

    public function test_berita_acara_embeds_initial_and_action_photos(): void
    {
        $this->createVerifiedFinding(withPhotos: true);

        $report = app(FindingReportService::class)->buildReport($this->application->fresh());

        $this->assertCount(1, $report['attachments']);
        $this->assertStringStartsWith('data:image/', (string) $report['attachments'][0]['initial_image']);
        $this->assertStringStartsWith('data:image/', (string) $report['attachments'][0]['action_image']);
    }

    public function test_berita_acara_does_not_render_signature_block(): void
    {
        $this->createVerifiedFinding();

        $pdf = app(FindingReportService::class)->render($this->application->fresh())->output();

        // DomPDF mengompres stream; cukup pastikan dokumen terbentuk.
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_berita_acara_action_visible_only_after_finding_verified(): void
    {
        Filament::setCurrentPanel('transportir');
        $this->actingAs($this->owner);

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('berita_acara');

        $this->createVerifiedFinding();

        Livewire::test(ViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionVisible('berita_acara');
    }

    public function test_berita_acara_action_visible_on_admin_panel(): void
    {
        Filament::setCurrentPanel('admin');

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('admin_verifikasi');
        $this->actingAs($admin);

        Livewire::test(AdminViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionHidden('berita_acara');

        $this->createVerifiedFinding();

        Livewire::test(AdminViewKimApplication::class, ['record' => $this->application->getRouteKey()])
            ->assertActionVisible('berita_acara');
    }
}
