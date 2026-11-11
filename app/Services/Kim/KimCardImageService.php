<?php

namespace App\Services\Kim;

use App\Models\KimCard;
use App\Services\Setting\SettingsService;
use App\Support\SafeFilename;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Encoder\QrCode as QrMatrix;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Render kartu KIM menjadi PNG siap cetak seukuran kartu ATM
 * (ISO/IEC 7810 ID-1: 85,60 × 53,98 mm) pada 300 DPI = 1011 × 638 px.
 *
 * Digambar langsung dengan GD (bukan rasterisasi PDF) supaya tidak butuh
 * biner sistem seperti ImageMagick/Ghostscript.
 */
class KimCardImageService
{
    private function disk(): string
    {
        return config('media.disk');
    }

    /** Ukuran kartu di 300 DPI (px). */
    public const WIDTH = 1011;

    public const HEIGHT = 638;

    /** Warna sesuai identitas panel (biru tua + abu netral). */
    private const COLOR_NAVY = [0x1E, 0x3A, 0x8A];

    private const COLOR_TEXT = [0x1A, 0x1A, 0x1A];

    private const COLOR_MUTED = [0x60, 0x60, 0x60];

    private const COLOR_BORDER = [0xD4, 0xD4, 0xD8];

    private const COLOR_WHITE = [0xFF, 0xFF, 0xFF];

    private GdImage $canvas;

    public function generate(KimCard $card): string
    {
        $this->ensureGd();

        $card->loadMissing('application.vehicle', 'application.transporter');

        $this->canvas = $this->createCanvas();
        $this->drawBackground();
        $this->drawHeader($card);
        $this->drawFields($card);

        if (app(SettingsService::class)->kimQrEnabled()) {
            $this->drawQrCode($card);
        }

        $this->drawFooter();

        $path = 'kim-cards/'.$this->safeFilename($card->kim_number);

        Storage::disk($this->disk())->put($path, $this->toPng());

        $card->update(['image_path' => $path]);

        return $path;
    }

    public function download(KimCard $card)
    {
        if (! $card->image_path || ! Storage::disk($this->disk())->exists($card->image_path)) {
            $this->generate($card);
            $card->refresh();
        }

        return Storage::disk($this->disk())->download(
            $card->image_path,
            $this->safeFilename($card->kim_number)
        );
    }

    private function ensureGd(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            throw new RuntimeException('Ekstensi GD (dengan FreeType) dibutuhkan untuk membuat gambar kartu KIM.');
        }
    }

    private function createCanvas(): GdImage
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($canvas === false) {
            throw new RuntimeException('Gagal membuat kanvas gambar kartu KIM.');
        }

        imageantialias($canvas, true);

        return $canvas;
    }

    private function drawBackground(): void
    {
        $white = $this->color(self::COLOR_WHITE);
        imagefilledrectangle($this->canvas, 0, 0, self::WIDTH, self::HEIGHT, $white);

        // Area header biru lebih tinggi agar proporsional dan tidak berhimpitan.
        imagefilledrectangle($this->canvas, 0, 0, self::WIDTH, 116, $this->color(self::COLOR_NAVY));

        // Garis aksen merah khas Pertamina di bawah header.
        imagefilledrectangle($this->canvas, 0, 116, self::WIDTH, 122, $this->color([0xDC, 0x26, 0x26]));

        // Bingkai kartu navy 2px.
        imagerectangle($this->canvas, 0, 0, self::WIDTH - 1, self::HEIGHT - 1, $this->color(self::COLOR_NAVY));
        imagerectangle($this->canvas, 1, 1, self::WIDTH - 2, self::HEIGHT - 2, $this->color(self::COLOR_NAVY));
    }

    private function drawHeader(KimCard $card): void
    {
        // Logo K3 di kiri dan PPN di kanan, mengikuti tata letak dokumen checklist.
        $logoK3Size = 76;
        $logoPpnWidth = 156;
        $logoPpnHeight = (int) round($logoPpnWidth * 199 / 800);

        $this->drawLogo('img/K3.png', 40, intdiv(116 - $logoK3Size, 2), $logoK3Size, $logoK3Size);
        $this->drawLogo(
            'img/ppn.png',
            self::WIDTH - 40 - $logoPpnWidth,
            intdiv(116 - $logoPpnHeight, 2),
            $logoPpnWidth,
            $logoPpnHeight
        );

        // Judul di tengah, di antara kedua logo.
        $centerLeft = 40 + $logoK3Size + 24;
        $centerRight = self::WIDTH - 40 - $logoPpnWidth - 24;
        $centerWidth = $centerRight - $centerLeft;

        $title = ($card->is_dispensation ? 'KIM — SEMENTARA' : 'KARTU IZIN MASUK (KIM)');
        $titleSize = 24;

        $this->text(
            $this->ellipsize($title, $centerWidth, $titleSize, true),
            $centerLeft + intdiv($centerWidth - $this->textWidth($title, $titleSize, true), 2),
            46,
            $titleSize,
            true,
            self::COLOR_WHITE
        );

        $subtitle = 'FUEL TERMINAL TEGAL';
        $subtitleSize = 14;

        $this->text(
            $subtitle,
            $centerLeft + intdiv($centerWidth - $this->textWidth($subtitle, $subtitleSize), 2),
            76,
            $subtitleSize,
            false,
            [0xC7, 0xD2, 0xF7]
        );
    }

    /**
     * Tempel logo PNG pada kanvas kartu dengan ukuran tertentu.
     */
    private function drawLogo(string $relativePath, int $x, int $y, int $width, int $height): void
    {
        $path = public_path($relativePath);

        if (! is_file($path)) {
            return;
        }

        $logo = @imagecreatefrompng($path);

        if ($logo === false) {
            return;
        }

        $sourceWidth = imagesx($logo);
        $sourceHeight = imagesy($logo);

        imagealphablending($this->canvas, true);
        imagesavealpha($this->canvas, true);

        imagecopyresampled(
            $this->canvas,
            $logo,
            $x,
            $y,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($logo);
    }

    private function drawFields(KimCard $card): void
    {
        $fields = [
            ['NOMOR KIM', (string) $card->kim_number],
            ['NOPOL', (string) $card->application->vehicle->registration_number],
            ['PERUSAHAAN', (string) $card->application->transporter->company_name],
            ['TANGGAL TERBIT', $card->issued_at->format('d M Y')],
            ['BERLAKU HINGGA', $card->expires_at->format('d M Y')],
        ];

        if ($card->is_dispensation && $card->dispensation_expires_at !== null) {
            $fields[] = ['BATAS DISPENSASI', $card->dispensation_expires_at->format('d M Y')];
        }

        $top = 152;
        // Tinggi baris disesuaikan agar tetap muat bila ada baris dispensasi.
        $rowHeight = count($fields) > 4 ? 68 : 88;
        $qrPanelLeft = self::WIDTH - 40 - 240; // ruang QR di sisi kanan
        $maxValueWidth = $qrPanelLeft - 40 - 24; // batas lebar teks kiri agar tidak nabrak QR

        foreach ($fields as $index => [$label, $value]) {
            $offset = $top + ($index * $rowHeight);

            $this->text($label, 40, $offset, 12, false, self::COLOR_MUTED);
            $this->text(
                $this->ellipsize($value, $maxValueWidth, 22, true),
                40,
                $offset + 28,
                22,
                true,
                self::COLOR_TEXT
            );
        }
    }

    private function drawQrCode(KimCard $card): void
    {
        $qrSize = 220;
        $matrix = $this->qrMatrix($card);
        $modules = $matrix->getMatrix()->getWidth();
        $moduleSize = max(2, intdiv($qrSize, $modules + 8));
        $rendered = ($modules + 8) * $moduleSize;

        $left = self::WIDTH - 40 - $rendered;
        $top = 160;

        // Panel putih berbingkai di belakang QR.
        imagefilledrectangle(
            $this->canvas,
            $left - 8,
            $top - 8,
            $left + $rendered + 8,
            $top + $rendered + 8,
            $this->color(self::COLOR_WHITE)
        );
        imagerectangle(
            $this->canvas,
            $left - 8,
            $top - 8,
            $left + $rendered + 8,
            $top + $rendered + 8,
            $this->color(self::COLOR_BORDER)
        );

        $black = $this->color(self::COLOR_TEXT);
        $quietZone = $moduleSize * 4;

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->getMatrix()->get($x, $y) !== 1) {
                    continue;
                }

                $x1 = $left + $quietZone + ($x * $moduleSize);
                $y1 = $top + $quietZone + ($y * $moduleSize);

                imagefilledrectangle($this->canvas, $x1, $y1, $x1 + $moduleSize - 1, $y1 + $moduleSize - 1, $black);
            }
        }

        $caption = 'Pindai untuk validasi';
        $captionSize = 14;
        $captionWidth = $this->textWidth($caption, $captionSize);

        $this->text(
            $caption,
            $left + intdiv($rendered - $captionWidth, 2),
            $top + $rendered + 24,
            $captionSize,
            false,
            self::COLOR_MUTED
        );
    }

    private function drawFooter(): void
    {
        $note = 'KIM ini sah hanya jika terverifikasi melalui halaman validasi resmi O-KIM Fuel Terminal Tegal.';
        $baseline = self::HEIGHT - 34;

        imageline($this->canvas, 40, $baseline - 34, self::WIDTH - 40, $baseline - 34, $this->color(self::COLOR_BORDER));

        $this->text(
            $this->ellipsize($note, self::WIDTH - 80, 14),
            40,
            $baseline,
            14,
            false,
            self::COLOR_MUTED
        );
    }

    private function qrMatrix(KimCard $card): QrMatrix
    {
        return Encoder::encode(
            app(KimQrCodeService::class)->validationUrl($card),
            ErrorCorrectionLevel::L()
        );
    }

    /**
     * Potong teks agar tidak melewati lebar maksimum (tambahkan elipsis).
     */
    private function ellipsize(string $value, int $maxWidth, int $size, bool $bold = false): string
    {
        if ($this->textWidth($value, $size, $bold) <= $maxWidth) {
            return $value;
        }

        while ($value !== '' && $this->textWidth($value.'…', $size, $bold) > $maxWidth) {
            $value = mb_substr($value, 0, -1);
        }

        return rtrim($value).'…';
    }

    private function textWidth(string $text, int $size, bool $bold = false): int
    {
        $box = imagettfbbox($size, 0, $this->font($bold), $text);

        return (int) abs($box[2] - $box[0]);
    }

    private function text(string $text, int $x, int $y, int $size, bool $bold = false, array $rgb = self::COLOR_TEXT): void
    {
        imagettftext($this->canvas, $size, 0, $x, $y, $this->color($rgb), $this->font($bold), $text);
    }

    private function font(bool $bold = false): string
    {
        $path = resource_path('fonts/'.($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));

        if (! is_file($path)) {
            throw new RuntimeException('Font kartu KIM tidak ditemukan: '.$path);
        }

        return $path;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function color(array $rgb): int
    {
        return imagecolorallocate($this->canvas, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function toPng(): string
    {
        ob_start();
        imagepng($this->canvas, null, 6);
        $binary = (string) ob_get_clean();

        if ($binary === '') {
            throw new RuntimeException('Gagal mengubah kartu KIM menjadi PNG.');
        }

        return $binary;
    }

    private function safeFilename(string $kimNumber): string
    {
        return SafeFilename::make($kimNumber.'.png', 'kim');
    }
}
