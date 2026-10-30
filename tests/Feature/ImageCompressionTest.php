<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\DocumentVerificationStatus;
use App\Enums\InspectionResultValue;
use App\Models\DocumentType;
use App\Models\InspectionItem;
use App\Models\InspectionResult;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Document\VehicleDocumentService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Inspection\InspectionService;
use App\Support\ImageCompressor;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kompresi gambar saat unggah demi menekan biaya penyimpanan media.
 */
class ImageCompressionTest extends TestCase
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
        Storage::fake(config('media.disk'));

        config([
            'media.disk' => 'local',
            'media.compression.enabled' => true,
            'media.compression.max_dimension' => 800,
            'media.compression.jpeg_quality' => 60,
        ]);

        $this->transporter = Transporter::factory()->create();

        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);
    }

    /**
     * Gambar JPEG besar dengan noise agar hasil kompresi pasti lebih kecil.
     */
    private function noisyJpeg(string $name = 'foto.jpg', int $width = 1200, int $height = 900): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                $color = imagecolorallocate(
                    $image,
                    random_int(0, 255),
                    random_int(0, 255),
                    random_int(0, 255),
                );
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
            }
        }

        ob_start();
        imagejpeg($image, null, 100);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        $path = tempnam(sys_get_temp_dir(), 'okim-test-').'.jpg';
        file_put_contents($path, $binary);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function stnkType(): DocumentType
    {
        return DocumentType::where('code', 'STNK')->firstOrFail();
    }

    private function makeApplication(): KimApplication
    {
        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            app(VehicleDocumentService::class)->upload(
                $this->vehicle,
                DocumentType::where('code', $code)->value('id'),
                UploadedFile::fake()->create($code.'.pdf', 50, 'application/pdf'),
                ['expires_at' => '2027-01-01'],
            );
        }

        $application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($application, $this->owner);

        $application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->hsse->id,
            'status' => ApplicationStatus::Inspection,
        ]);

        return $application->fresh();
    }

    private function hsseResult(): InspectionResult
    {
        $item = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('item_code', 'HSSE-01')->firstOrFail();

        return app(InspectionService::class)->recordResult(
            $this->makeApplication(),
            $item,
            InspectionResultValue::Compliant,
            $this->hsse,
        );
    }

    // ---------- Unit kompresor ----------

    public function test_compressor_shrinks_large_jpeg_and_records_new_size(): void
    {
        $file = $this->noisyJpeg();
        $originalSize = $file->getSize();

        [$compressed, $mimeType, $size] = app(ImageCompressor::class)->compress($file);

        $this->assertLessThan($originalSize, $size);
        // Gambar dikonversi ke WebP agar lebih hemat.
        $this->assertSame('image/webp', $mimeType);
        $this->assertStringEndsWith('.webp', $compressed->getRealPath());
        $this->assertSame('foto.webp', $compressed->getClientOriginalName());
        $this->assertLessThanOrEqual(800, max(getimagesize($compressed->getRealPath())[0], getimagesize($compressed->getRealPath())[1]));
    }

    public function test_png_image_is_converted_to_webp(): void
    {
        $image = imagecreatetruecolor(1200, 900);

        for ($x = 0; $x < 1200; $x += 3) {
            for ($y = 0; $y < 900; $y += 3) {
                imagefilledrectangle(
                    $image, $x, $y, $x + 2, $y + 2,
                    imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255))
                );
            }
        }

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        $path = tempnam(sys_get_temp_dir(), 'okim-test-').'.png';
        file_put_contents($path, $binary);

        $file = new UploadedFile($path, 'screenshot.png', 'image/png', null, true);

        [$compressed, $mimeType, $size] = app(ImageCompressor::class)->compress($file);

        $this->assertSame('image/webp', $mimeType);
        $this->assertSame('screenshot.webp', $compressed->getClientOriginalName());
        $this->assertLessThan($file->getSize(), $size);
    }

    public function test_webp_conversion_can_be_disabled(): void
    {
        config(['media.compression.convert_to_webp' => false]);

        $file = $this->noisyJpeg();

        [$compressed, $mimeType] = app(ImageCompressor::class)->compress($file);

        $this->assertSame('image/jpeg', $mimeType);
        $this->assertSame('foto.jpg', $compressed->getClientOriginalName());
    }

    public function test_compressor_reaches_target_size_for_large_photo(): void
    {
        config([
            'media.compression.max_dimension' => 1600,
            'media.compression.target_kilobytes' => 500,
            'media.compression.jpeg_quality' => 72,
        ]);

        $file = $this->noisyJpeg('besar.jpg', 2400, 1800);
        $originalSize = $file->getSize();

        [$compressed, , $size] = app(ImageCompressor::class)->compress($file);

        $this->assertLessThanOrEqual(500 * 1024, $size);
        $this->assertLessThan($originalSize, $size);

        [$width, $height] = getimagesize($compressed->getRealPath());
        $this->assertLessThanOrEqual(1600, max($width, $height));
    }

    public function test_compressor_keeps_quality_when_target_not_reachable(): void
    {
        // Target tidak realistis: kompresor tidak boleh menghasilkan file rusak.
        config(['media.compression.target_kilobytes' => 1]);

        $file = $this->noisyJpeg('besar.jpg', 1200, 900);

        [$compressed, , $size] = app(ImageCompressor::class)->compress($file);

        $this->assertGreaterThan(0, $size);
        $this->assertNotFalse(getimagesize($compressed->getRealPath()));
    }

    public function test_compressor_leaves_pdf_untouched(): void
    {
        $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');
        $originalSize = $file->getSize();

        [$result, $mimeType, $size] = app(ImageCompressor::class)->compress($file);

        $this->assertSame($file, $result);
        $this->assertSame('application/pdf', $mimeType);
        $this->assertSame($originalSize, $size);
    }

    public function test_compressor_can_be_disabled(): void
    {
        config(['media.compression.enabled' => false]);

        $file = $this->noisyJpeg();
        $originalSize = $file->getSize();

        [$result, , $size] = app(ImageCompressor::class)->compress($file);

        $this->assertSame($file, $result);
        $this->assertSame($originalSize, $size);
    }

    // ---------- Integrasi jalur unggah ----------

    public function test_vehicle_document_image_is_stored_compressed(): void
    {
        $file = $this->noisyJpeg();
        $originalSize = $file->getSize();

        $document = app(VehicleDocumentService::class)->upload(
            $this->vehicle,
            $this->stnkType()->id,
            $file,
            ['expires_at' => '2027-01-01'],
        );

        $storedSize = Storage::disk(config('media.disk'))->size($document->file_path);

        $this->assertLessThan($originalSize, $storedSize);
        $this->assertSame($storedSize, $document->file_size);
        // mime_type mencatat format hasil konversi, bukan format asli.
        $this->assertSame('image/webp', $document->mime_type);
        $this->assertStringEndsWith('.webp', $document->file_path);
        $this->assertSame(DocumentVerificationStatus::Pending, $document->verification_status);
    }

    public function test_inspection_attachment_image_is_stored_compressed(): void
    {
        $result = $this->hsseResult();
        $file = $this->noisyJpeg('bukti.jpg');
        $originalSize = $file->getSize();

        $attachment = app(InspectionAttachmentService::class)->upload($result, $this->hsse, [$file])[0];

        $storedSize = Storage::disk(config('media.disk'))->size($attachment->file_path);

        $this->assertLessThan($originalSize, $storedSize);
        $this->assertSame($storedSize, $attachment->file_size);
        $this->assertSame('bukti.webp', $attachment->original_filename);
    }

    public function test_compression_updates_extension_when_converted(): void
    {
        $result = $this->hsseResult();

        $attachment = app(InspectionAttachmentService::class)->upload(
            $result,
            $this->hsse,
            [$this->noisyJpeg('nama asli.JPG')],
        )[0];

        // Nama dasar dipertahankan, ekstensi mengikuti format hasil (WebP).
        $this->assertSame('nama asli.webp', $attachment->original_filename);
        $this->assertStringEndsWith('.webp', $attachment->file_path);
    }
}
