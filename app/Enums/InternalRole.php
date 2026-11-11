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
    case PengawasHsse = 'pengawas_hsse';
    case PengawasQq = 'pengawas_qq';
    case PengawasDistribusi = 'pengawas_distribusi';
    case Rsd = 'rsd';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminVerifikasi => 'Admin Verifikasi',
            self::Hsse => 'HSSE (TAD)',
            self::Distribusi => 'Distribusi (TAD)',
            self::Qq => 'QQ (TAD)',
            self::Ftm => 'Fuel Terminal Manager',
            self::PengawasHsse => 'Pengawas HSSE',
            self::PengawasQq => 'Pengawas QQ',
            self::PengawasDistribusi => 'Pengawas Distribusi',
            self::Rsd => 'Pengawas RSD',
        };
    }

    /** Role internal TAD: hanya boleh mengisi checklist. */
    public const TAD_ROLES = [
        self::Hsse->value,
        self::Qq->value,
        self::Distribusi->value,
    ];

    public function isTad(): bool
    {
        return in_array($this->value, self::TAD_ROLES, true);
    }

    public function isPengawas(): bool
    {
        return str_starts_with($this->value, 'pengawas_');
    }

    /** @return array<self> */
    public static function tadCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role->isTad()));
    }

    /** @return array<self> */
    public static function pengawasCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role->isPengawas()));
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
