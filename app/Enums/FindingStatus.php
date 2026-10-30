<?php

namespace App\Enums;

enum FindingStatus: string
{
    case Open = 'open';
    case SubmittedForVerification = 'submitted_for_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Dispensation = 'dispensation';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::SubmittedForVerification => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Tindak Lanjut Ditolak',
            self::Dispensation => 'Dispensasi FTM',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::SubmittedForVerification => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
            self::Dispensation => 'info',
        };
    }

    /**
     * Temuan dianggap selesai bila sudah diverifikasi atau didispensasi FTM.
     */
    public function isResolved(): bool
    {
        return in_array($this, [self::Verified, self::Dispensation], true);
    }

    /**
     * Status yang boleh ditindaklanjuti transportir.
     *
     * @return array<self>
     */
    public static function actionableCases(): array
    {
        return [self::Open, self::Rejected, self::Dispensation];
    }
}
