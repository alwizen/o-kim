<?php

namespace App\Enums;

enum InspectionResultValue: string
{
    case Compliant = 'compliant';
    case Finding = 'finding';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Compliant => 'Sesuai',
            self::Finding => 'Temuan',
            self::NotApplicable => 'Tidak Berlaku',
        };
    }
}
