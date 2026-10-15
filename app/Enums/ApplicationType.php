<?php

namespace App\Enums;

enum ApplicationType: string
{
    case New = 'new';
    case Renewal = 'renewal';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Renewal => 'Perpanjangan',
        };
    }
}
