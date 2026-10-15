<?php

namespace App\Enums;

enum KimCardStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Expired => 'Kedaluwarsa',
            self::Revoked => 'Dicabut',
        };
    }
}
