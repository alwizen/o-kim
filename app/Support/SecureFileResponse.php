<?php

namespace App\Support;

class SecureFileResponse
{
    /**
     * Header untuk pratinjau inline: cegah MIME sniffing dan batasi eksekusi konten.
     */
    public const PREVIEW_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        'X-Frame-Options' => 'SAMEORIGIN',
    ];

    /**
     * Header untuk unduhan: cegah browser menebak tipe konten.
     */
    public const DOWNLOAD_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
    ];
}
