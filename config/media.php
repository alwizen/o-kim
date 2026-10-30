<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk Penyimpanan Media
    |--------------------------------------------------------------------------
    |
    | Semua berkas unggahan (dokumen kendaraan, lampiran pemeriksaan, bukti
    | tindak lanjut temuan, PDF & gambar kartu KIM) disimpan pada satu disk
    | yang sama. Nilai default "local" agar pengembangan dan pengujian tetap
    | berjalan tanpa kredensial cloud.
    |
    | Untuk produksi, set MEDIA_DISK=r2 dan lengkapi variabel R2_* pada .env.
    | Disk bersifat privat: akses hanya melalui route ber-otorisasi milik
    | aplikasi (bukan URL publik bucket).
    |
    */

    'disk' => env('MEDIA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Kompresi Gambar
    |--------------------------------------------------------------------------
    |
    | Gambar unggahan (JPG/PNG/WebP) diperkecil dimensinya dan di-encode ulang
    | sebelum disimpan untuk menghemat storage. Hasil kompresi hanya dipakai
    | bila ukurannya benar-benar lebih kecil dari berkas asli.
    |
    | PDF dan format lain tidak diubah.
    |
    */

    'compression' => [
        'enabled' => env('MEDIA_COMPRESS_ENABLED', true),

        // Sisi terpanjang maksimum (piksel). 0 = tanpa pengecilan.
        // 1600px cukup tajam untuk dibaca/dipratinjau dan jauh lebih hemat.
        'max_dimension' => (int) env('MEDIA_COMPRESS_MAX_DIMENSION', 1600),

        /*
         * Konversi gambar (JPEG/PNG) ke WebP. WebP jauh lebih kecil pada mutu
         * setara. PDF dan format non-gambar tidak pernah dikonversi.
         */
        'convert_to_webp' => env('MEDIA_COMPRESS_TO_WEBP', true),

        // Kualitas awal encode JPEG (dipakai bila konversi WebP dimatikan).
        'jpeg_quality' => (int) env('MEDIA_COMPRESS_JPEG_QUALITY', 72),

        // Kualitas WebP (0-100).
        'webp_quality' => (int) env('MEDIA_COMPRESS_WEBP_QUALITY', 72),

        // Tingkat kompresi PNG (0-9).
        'png_compression' => (int) env('MEDIA_COMPRESS_PNG_COMPRESSION', 9),

        /*
         * Target ukuran akhir per berkas (KB). Bila hasil masih lebih besar,
         * kualitas diturunkan bertahap (minimum 40) lalu dimensi diperkecil
         * hingga 3 kali. 0 = target dinonaktifkan.
         */
        'target_kilobytes' => (int) env('MEDIA_COMPRESS_TARGET_KB', 500),
    ],

];
