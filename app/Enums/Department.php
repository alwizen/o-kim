<?php

namespace App\Enums;

enum Department: string
{
    case Hsse = 'hsse';
    case Distribusi = 'distribusi';
    case Qq = 'qq';
    case Ftm = 'ftm';

    public function label(): string
    {
        return match ($this) {
            self::Hsse => 'HSSE',
            self::Distribusi => 'Distribusi',
            self::Qq => 'QQ',
            self::Ftm => 'Fuel Terminal Manager',
        };
    }

    public function inspectPermission(): string
    {
        return 'inspect_'.$this->value;
    }

    /** @return array<self> */
    public static function inspectingDepartments(): array
    {
        return [self::Hsse, self::Distribusi, self::Qq];
    }
}
