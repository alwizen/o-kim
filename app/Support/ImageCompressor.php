<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Kompresi gambar sebelum disimpan ke media storage.
 *
 * Tujuan: menekan biaya penyimpanan (R2) dan mempercepat unduhan.
 *
 * Strategi:
 * 1. Perkecil dimensi ke sisi terpanjang `max_dimension`.
 * 2. Konversi ke WebP (bila `convert_to_webp` aktif) atau encode ulang pada
 *    format aslinya, dengan kualitas awal `webp_quality` / `jpeg_quality`.
 * 3. Bila hasil masih di atas `target_kilobytes`, turunkan kualitas bertahap
 *    (dan bila perlu perkecil dimensi lagi) sampai target tercapai.
 * 4. Hasil hanya dipakai bila benar-benar lebih kecil dari berkas asli;
 *    bila tidak, berkas asli dipertahankan agar tidak ada penurunan mutu
 *    tanpa manfaat.
 *
 * Hanya berkas gambar (JPEG/PNG/WebP) yang disentuh. PDF dan format lain
 * diteruskan apa adanya tanpa perubahan apa pun.
 */
class ImageCompressor
{
    private const COMPRESSIBLE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** Batas bawah kualitas agar hasil tidak rusak secara visual. */
    private const MIN_QUALITY = 40;

    /** Batas percobaan pengecilan dimensi tambahan saat mengejar target. */
    private const MAX_RESIZE_STEPS = 3;

    /**
     * Kompres berkas unggahan bila memungkinkan.
     *
     * @return array{0: UploadedFile, 1: string, 2: int} [berkas, mime, ukuran]
     */
    public function compress(UploadedFile $file): array
    {
        $fallback = [$file, (string) $file->getMimeType(), (int) $file->getSize()];

        if (! $this->isEnabled() || ! $this->isCompressible($file)) {
            return $fallback;
        }

        $outputMime = $this->outputMime($file);
        $binary = $this->compressBinary($file, $outputMime);

        if ($binary === null) {
            return $fallback;
        }

        // Jangan simpan hasil yang justru lebih besar dari aslinya.
        if (strlen($binary) >= $fallback[2]) {
            return $fallback;
        }

        $path = tempnam(sys_get_temp_dir(), 'okim-img-');

        if ($path === false) {
            return $fallback;
        }

        $extension = $outputMime === $file->getMimeType()
            ? Str::lower($file->getClientOriginalExtension())
            : self::mimeExtension($outputMime);

        $path .= '.'.$extension;

        if (file_put_contents($path, $binary) === false) {
            @unlink($path);

            return $fallback;
        }

        // Nama asli ikut disesuaikan bila format berubah (mis. foto.jpg -> foto.webp)
        // agar ekstensi yang diunduh pengguna sesuai isi berkas.
        $originalName = $outputMime === $file->getMimeType()
            ? $file->getClientOriginalName()
            : self::replaceExtension($file->getClientOriginalName(), $extension);

        $compressed = new UploadedFile(
            $path,
            $originalName,
            $outputMime,
            null,
            true,
        );

        return [$compressed, $outputMime, strlen($binary)];
    }

    /**
     * Format keluaran: WebP bila diaktifkan dan GD mendukungnya.
     */
    private function outputMime(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();

        if (! (bool) config('media.compression.convert_to_webp', true)) {
            return $mime;
        }

        if (! function_exists('imagewebp') || ! (imagetypes() & IMG_WEBP)) {
            return $mime;
        }

        return 'image/webp';
    }

    private static function mimeExtension(string $mime): string
    {
        return match ($mime) {
            'image/webp' => 'webp',
            'image/png' => 'png',
            default => 'jpg',
        };
    }

    private static function replaceExtension(string $filename, string $extension): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        return $base.'.'.$extension;
    }

    public function isEnabled(): bool
    {
        return (bool) config('media.compression.enabled', true)
            && extension_loaded('gd');
    }

    /**
     * Hanya gambar yang didukung GD yang dikompres.
     */
    private function isCompressible(UploadedFile $file): bool
    {
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, self::COMPRESSIBLE_MIME_TYPES, true)) {
            return false;
        }

        return @getimagesize($file->getRealPath()) !== false;
    }

    /**
     * Skala turun + encode ulang hingga target ukuran tercapai.
     * Mengembalikan null bila gagal.
     */
    private function compressBinary(UploadedFile $file, string $outputMime): ?string
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            return null;
        }

        $info = @getimagesize($realPath);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        $mime = (string) ($info['mime'] ?? $file->getMimeType());

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
            default => null,
        };

        if (! $source instanceof \GdImage) {
            return null;
        }

        try {
            $maxDimension = (int) config('media.compression.max_dimension', 1920);
            $targetBytes = $this->targetBytes();

            $best = null;
            $targetWidth = (int) $width;

            for ($step = 0; $step <= self::MAX_RESIZE_STEPS; $step++) {
                $scaled = $this->resize($source, $width, $height, $maxDimension);
                $isCopy = $scaled === $source;

                try {
                    $candidate = $this->encodeToTarget($scaled, $outputMime, $targetBytes);
                } finally {
                    if (! $isCopy) {
                        imagedestroy($scaled);
                    }
                }

                if ($candidate === null) {
                    break;
                }

                // Simpan hasil terkecil yang pernah dicapai.
                if ($best === null || strlen($candidate) < strlen($best)) {
                    $best = $candidate;
                }

                if ($targetBytes === null || strlen($candidate) <= $targetBytes) {
                    break;
                }

                // Masih di atas target: perkecil dimensi untuk putaran berikutnya.
                $targetWidth = (int) round($targetWidth * 0.8);

                if ($targetWidth < 320) {
                    break;
                }

                $maxDimension = $targetWidth;
            }

            return $best;
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Ukuran target dalam byte, atau null bila target tidak diaktifkan.
     */
    private function targetBytes(): ?int
    {
        $kilobytes = (int) config('media.compression.target_kilobytes', 0);

        return $kilobytes > 0 ? $kilobytes * 1024 : null;
    }

    /**
     * Encode dengan kualitas menurun bertahap sampai target tercapai.
     */
    private function encodeToTarget(\GdImage $image, string $mime, ?int $targetBytes): ?string
    {
        $quality = $this->startingQuality($mime);
        $best = null;

        while ($quality >= self::MIN_QUALITY) {
            $candidate = $this->encode($image, $mime, $quality);

            if ($candidate === null) {
                return $best;
            }

            if ($best === null || strlen($candidate) < strlen($best)) {
                $best = $candidate;
            }

            if ($targetBytes === null || strlen($candidate) <= $targetBytes) {
                return $candidate;
            }

            $quality -= 10;
        }

        return $best;
    }

    private function startingQuality(string $mime): int
    {
        return match ($mime) {
            'image/jpeg' => (int) config('media.compression.jpeg_quality', 72),
            'image/webp' => (int) config('media.compression.webp_quality', 72),
            default => 100,
        };
    }

    private function resize(\GdImage $source, int $width, int $height, int $maxDimension): \GdImage
    {
        if ($maxDimension <= 0 || max($width, $height) <= $maxDimension) {
            return $source;
        }

        $ratio = $maxDimension / max($width, $height);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            return $source;
        }

        // Pertahankan transparansi untuk PNG/WebP.
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        return $target;
    }

    private function encode(\GdImage $image, string $mime, int $quality): ?string
    {
        $flattened = null;

        // JPEG tidak mendukung alpha; ratakan ke latar putih agar tidak hitam.
        if ($mime === 'image/jpeg') {
            $flattened = $this->flattenOnWhite($image);
            $image = $flattened;
        }

        try {
            ob_start();

            $ok = match ($mime) {
                'image/jpeg' => imageinterlace($image, true) && imagejpeg($image, null, $quality),
                'image/png' => imagepng($image, null, (int) config('media.compression.png_compression', 9)),
                'image/webp' => imagewebp($image, null, $quality),
                default => false,
            };

            $binary = (string) ob_get_clean();
        } finally {
            if ($flattened !== null) {
                imagedestroy($flattened);
            }
        }

        if (! $ok || $binary === '') {
            return null;
        }

        return $binary;
    }

    /**
     * Ratakan gambar transparan ke latar putih (untuk ekspor JPEG).
     */
    private function flattenOnWhite(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            return $image;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white !== false) {
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        return $canvas;
    }
}
