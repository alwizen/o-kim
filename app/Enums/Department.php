<?php

namespace App\Enums;

enum Department: string
{
    case Hsse = 'hsse';
    case Distribusi = 'distribusi';
    case Qq = 'qq';
    case Rsd = 'rsd';
    case Ftm = 'ftm';

    public function label(): string
    {
        return match ($this) {
            self::Hsse => 'HSSE',
            self::Distribusi => 'Distribusi',
            self::Qq => 'QQ',
            self::Rsd => 'RSD',
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

    /**
     * Fungsi yang wajib menyetujui sebelum approval final FTM.
     * Mengisi: tiga fungsi pemeriksa ditambah Pengawas RSD (wakil FTM).
     *
     * @return array<self>
     */
    public static function approvableDepartments(): array
    {
        return [...self::inspectingDepartments(), self::Rsd];
    }

    /**
     * Urutan lengkap tahap approval pada stepper: fungsi, RSD, lalu FTM.
     *
     * @return array<self>
     */
    public static function approvalSteps(): array
    {
        return [...self::approvableDepartments(), self::Ftm];
    }
}
