<?php

namespace App\Support;

class SafeFilename
{
    /**
     * Ubah nama berkas menjadi bentuk aman untuk header unduhan.
     *
     * Mengganti karakter "/" dan "\" (ditolak header Content-Disposition) dengan "-",
     * lalu menghapus karakter di luar huruf, angka, titik, garis bawah, dan tanda hubung.
     * Ekstensi asli dipertahankan.
     */
    public static function make(string $filename, string $fallback = 'berkas'): string
    {
        $filename = str_replace(['/', '\\'], '-', $filename);

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?? '';
        $name = trim($name, '-_.');
        $name = preg_replace('/-+/', '-', $name) ?? $name;

        if ($name === '') {
            $name = $fallback;
        }

        $extension = preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '';

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }
}
