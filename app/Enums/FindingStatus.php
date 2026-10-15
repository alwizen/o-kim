<?php

namespace App\Enums;

enum FindingStatus: string
{
    case Open = 'open';
    case SubmittedForVerification = 'submitted_for_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::SubmittedForVerification => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Tindak Lanjut Ditolak',
        };
    }

    public function isResolved(): bool
    {
        return $this === self::Verified;
    }
}
