<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Ubah berkas gambar pada media disk menjadi data URI yang dapat dibaca DomPDF.
 *
 * DomPDF belum mendukung WebP, sehingga gambar WebP dikonversi ke PNG lebih
 * dulu. Bila konversi gagal (mis. GD tanpa dukungan WebP) gambar dilewati agar
 * dokumen tetap dapat tercetak.
 */
class PdfImage
{
    /**
     * Batas lebar/tinggi gambar setelah dikonversi (px) agar PDF tidak membengkak.
     */
    public const DEFAULT_MAX_PX = 900;

    public static function dataUri(
        string $filePath,
        string $mimeType,
        string $disk,
        int $maxPx = self::DEFAULT_MAX_PX,
    ): ?string {
        $storage = Storage::disk($disk);

        if (! $storage->exists($filePath)) {
            return null;
        }

        $binary = $storage->get($filePath);

        if ($binary === null || $binary === '') {
            return null;
        }

        $mime = $mimeType;

        // DomPDF hanya mengenali JPEG dan PNG dengan aman.
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $binary = self::convertToPng($binary, $maxPx);

            if ($binary === null) {
                return null;
            }

            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    /**
     * Konversi biner gambar (mis. WebP) ke PNG dan kecilkan bila sangat besar.
     */
    private static function convertToPng(string $binary, int $maxPx): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxPx > 0 && ($width > $maxPx || $height > $maxPx)) {
            $scale = $maxPx / max($width, $height);
            $target = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

            if ($target !== false) {
                $image = $target;
            }
        }

        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();

        return is_string($png) && $png !== '' ? $png : null;
    }
}
