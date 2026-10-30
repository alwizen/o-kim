<?php

namespace App\Enums;

enum DispensationAction: string
{
    case Granted = 'granted';
    case Extended = 'extended';
    case ReasonUpdated = 'reason_updated';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Dispensasi Diberikan',
            self::Extended => 'Perpanjangan Dispensasi',
            self::ReasonUpdated => 'Perubahan Alasan',
            self::Revoked => 'Dispensasi Dicabut',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Granted => 'warning',
            self::Extended => 'info',
            self::ReasonUpdated => 'gray',
            self::Revoked => 'danger',
        };
    }
}
