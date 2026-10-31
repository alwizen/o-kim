<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute web hanya untuk akun yang sudah disetujui.
 *
 * Filament sudah menolak akun non-approved lewat `User::canAccessPanel()`, tetapi
 * rute di routes/web.php hanya memakai middleware `auth`. Middleware ini menutup
 * celah tersebut pada level rute, termasuk untuk rute yang akan ditambahkan nanti.
 *
 * Rute swalayan (halaman tunggu, logout, dan pengisian profil transportir)
 * dikecualikan karena justru dibutuhkan sebelum akun disetujui.
 */
class EnsureAccountIsApproved
{
    /**
     * Nama rute yang tetap boleh diakses akun belum disetujui.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROUTE_NAMES = [
        'logout',
        'registration.pending.internal',
        'registration.pending.transporter',
        'registration.profile.edit',
        'registration.profile.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->account_status === AccountStatus::Approved) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        abort(403, 'Akun Anda belum disetujui.');
    }

    private function isAllowedRoute(Request $request): bool
    {
        $name = $request->route()?->getName();

        return is_string($name) && in_array($name, self::ALLOWED_ROUTE_NAMES, true);
    }
}
