<?php

namespace App\Enums;

enum DocumentScanType: string
{
    case BeritaAcara = 'ba';
    case Kim = 'kim';

    public function label(): string
    {
        return match ($this) {
            self::BeritaAcara => 'Berita Acara',
            self::Kim => 'Kartu KIM',
        };
    }
}
