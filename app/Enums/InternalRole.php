<?php

namespace App\Enums;

enum InternalRole: string
{
    case SuperAdmin = 'super_admin';
    case AdminVerifikasi = 'admin_verifikasi';
    case Hsse = 'hsse';
    case Distribusi = 'distribusi';
    case Qq = 'qq';
    case Ftm = 'ftm';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminVerifikasi => 'Admin Verifikasi',
            self::Hsse => 'HSE',
            self::Distribusi => 'Distribusi',
            self::Qq => 'QQ',
            self::Ftm => 'Fuel Terminal Manager',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
