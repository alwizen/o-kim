<?php

namespace App\Support\Widget;

use App\Enums\Department;
use App\Models\User;

/**
 * Penentu fungsi (departemen) yang boleh diakses seorang petugas.
 *
 * Dipakai widget dashboard agar TAD/Pengawas hanya melihat antrean fungsinya
 * sendiri, bukan seluruh fungsi. Tanpa ini, TAD HSSE dapat melihat antrean
 * Distribusi (kebocoran data antar-fungsi).
 */
class DepartmentScope
{
    /**
     * Fungsi yang boleh diinspeksi user (berdasarkan permission `inspect_*`).
     *
     * @return array<int, Department>
     */
    public static function inspectable(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            Department::inspectingDepartments(),
            fn (Department $department): bool => $user->can('inspect_'.$department->value),
        ));
    }

    /**
     * Nilai string fungsi yang dapat diinspeksi user.
     *
     * @return array<int, string>
     */
    public static function inspectableValues(?User $user = null): array
    {
        return array_map(fn (Department $department): string => $department->value, self::inspectable($user));
    }

    /**
     * Fungsi yang dapat di-approve user (berdasarkan permission `approve_*`).
     *
     * @return array<int, Department>
     */
    public static function approvable(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            Department::approvableDepartments(),
            fn (Department $department): bool => $user->can('approve_'.$department->value),
        ));
    }

    /**
     * Nilai string fungsi yang dapat di-approve user.
     *
     * @return array<int, string>
     */
    public static function approvableValues(?User $user = null): array
    {
        return array_map(fn (Department $department): string => $department->value, self::approvable($user));
    }

    /**
     * Apakah user adalah petugas TAD (punya salah satu permission inspect_*).
     */
    public static function isInspector(?User $user = null): bool
    {
        return self::inspectable($user) !== [];
    }

    /**
     * Apakah user adalah Pengawas (punya salah satu permission approve_*).
     */
    public static function isApprover(?User $user = null): bool
    {
        return self::approvable($user) !== [];
    }
}
