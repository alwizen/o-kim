<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Kim\KimValidityService;
use App\Services\Setting\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Label sumber masa berlaku KIM: dokumen wajib terawal, batas maksimum
 * pengaturan, atau batas dispensasi.
 */
class KimValiditySourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function application(): KimApplication
    {
        $transporter = Transporter::factory()->create();
        $vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);

        return KimApplication::factory()->create([
            'transporter_id' => $transporter->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    private function verifiedDocument(KimApplication $application, string $name, ?string $expiresAt): void
    {
        $type = DocumentType::create([
            'code' => strtoupper($name),
            'name' => $name,
            'is_required' => true,
            'is_active' => true,
            'has_expiry_date' => true,
        ]);

        VehicleDocument::factory()->create([
            'vehicle_id' => $application->vehicle_id,
            'document_type_id' => $type->id,
            'verification_status' => DocumentVerificationStatus::Verified,
            'expires_at' => $expiresAt,
            'replaced_by_id' => null,
        ]);
    }

    private function service(): KimValidityService
    {
        return app(KimValidityService::class);
    }

    public function test_document_with_earliest_expiry_is_the_basis(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();

        $this->verifiedDocument($application, 'STNK', $issuedAt->copy()->addMonths(4)->toDateString());
        $this->verifiedDocument($application, 'TERA', $issuedAt->copy()->addMonths(2)->toDateString());

        $source = $this->service()->resolveValidity($application, $issuedAt);

        $this->assertSame('document', $source['basis']);
        $this->assertSame('TERA', $source['document_type']);
        $this->assertSame(
            $issuedAt->copy()->addMonths(2)->toDateString(),
            $source['expires_at']->toDateString(),
        );
    }

    public function test_label_names_the_document(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();

        $this->verifiedDocument($application, 'TERA', $issuedAt->copy()->addMonths(2)->toDateString());

        // Label memakai penerbitan KIM bila ada; di sini dihitung dari sekarang.
        $this->assertSame(
            ' (Dokumen TERA)',
            $this->service()->validityLabel($application),
        );
    }

    public function test_max_months_is_used_when_documents_expire_later(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();

        // Jauh melampaui batas maksimum (default 6 bulan).
        $this->verifiedDocument($application, 'STNK', $issuedAt->copy()->addYears(5)->toDateString());

        $source = $this->service()->resolveValidity($application, $issuedAt);

        $this->assertSame('max_months', $source['basis']);
        $this->assertNull($source['document_type']);
        $this->assertSame(
            $issuedAt->copy()->addMonths(app(SettingsService::class)->kimValidityMonths())->toDateString(),
            $source['expires_at']->toDateString(),
        );
    }

    public function test_label_falls_back_to_max_months(): void
    {
        $application = $this->application();
        $this->verifiedDocument($application, 'STNK', now()->addYears(5)->toDateString());

        $this->assertStringContainsString(
            'Batas Maksimum',
            $this->service()->validityLabel($application),
        );
    }

    public function test_dispensation_limit_wins(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();

        $this->verifiedDocument($application, 'TERA', $issuedAt->copy()->addMonths(2)->toDateString());

        $source = $this->service()->resolveValidity(
            $application,
            $issuedAt,
            $issuedAt->copy()->addMonth(),
        );

        $this->assertSame('dispensation', $source['basis']);
        $this->assertSame(
            $issuedAt->copy()->addMonth()->toDateString(),
            $source['expires_at']->toDateString(),
        );
    }

    public function test_calculate_expires_at_is_unchanged(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();

        $this->verifiedDocument($application, 'TERA', $issuedAt->copy()->addMonths(2)->toDateString());

        $this->assertSame(
            $issuedAt->copy()->addMonths(2)->toDateString(),
            $this->service()->calculateExpiresAt($application, $issuedAt)->toDateString(),
        );
    }

    public function test_document_without_expiry_date_is_skipped(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();

        $type = DocumentType::create([
            'code' => 'PTO',
            'name' => 'PTO',
            'is_required' => true,
            'is_active' => true,
            'has_expiry_date' => false,
        ]);

        VehicleDocument::factory()->create([
            'vehicle_id' => $application->vehicle_id,
            'document_type_id' => $type->id,
            'verification_status' => DocumentVerificationStatus::Verified,
            'expires_at' => null,
            'replaced_by_id' => null,
        ]);

        $source = $this->service()->resolveValidity($application, $issuedAt);

        $this->assertSame('max_months', $source['basis']);
    }

    public function test_pdf_renders_the_validity_label(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $application = $this->application();
        $issuedAt = now()->startOfDay();
        $this->verifiedDocument($application, 'TERA', $issuedAt->copy()->addMonths(2)->toDateString());

        $card = $application->kimCard()->create([
            'kim_number' => 'KIM/UJI/001',
            'qr_token' => (string) \Illuminate\Support\Str::uuid(),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addMonths(2),
        ]);

        $html = view('kim.card', [
            'card' => $card->load('application.vehicle', 'application.transporter'),
            'qrSvg' => '',
            'validityLabel' => $this->service()->validityLabel($application),
        ])->render();

        $this->assertStringContainsString('(Dokumen TERA)', $html);
    }

    public function test_validation_page_shows_the_validity_label(): void
    {
        $application = $this->application();
        $issuedAt = now()->startOfDay();
        $this->verifiedDocument($application, 'TERA', $issuedAt->copy()->addMonths(2)->toDateString());

        $card = $application->kimCard()->create([
            'kim_number' => 'KIM/UJI/002',
            'qr_token' => (string) \Illuminate\Support\Str::uuid(),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addMonths(2),
        ]);

        $this->get(route('kim.validate', ['token' => $card->qr_token]))
            ->assertSuccessful()
            ->assertSee('(Dokumen TERA)');
    }
}
