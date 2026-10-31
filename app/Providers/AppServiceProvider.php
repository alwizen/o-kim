<?php

namespace App\Providers;

use App\Enums\AccountStatus;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceApprovedAccountStatus();

        // Tautan "Lupa kata sandi?" pada halaman login panel admin.
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => Blade::render(<<<'BLADE'
                <div class="text-center">
                    <a href="{{ route('password.request') }}"
                       class="text-sm font-medium text-primary-600 underline-offset-4 hover:underline">
                        Lupa kata sandi?
                    </a>
                </div>
            BLADE),
        );
    }

    /**
     * Tolak seluruh ability untuk akun yang belum disetujui (pending/suspended/rejected).
     *
     * Sebagian role operasional (TAD, pengawas, FTM) memegang permission seperti
     * `access_admin_panel` dan `view_applications` sejak akun dibuat. Tanpa guard ini,
     * akun berstatus pending hasil pendaftaran mandiri dapat menembus policy — mis.
     * `KimApplicationPolicy::view()` — dan mengunduh laporan perusahaan lain
     * sebelum disetujui admin. Filament sudah memblokir panelnya lewat
     * `User::canAccessPanel()`, tetapi rute web di routes/web.php hanya memakai `auth`.
     */
    private function enforceApprovedAccountStatus(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (! $user instanceof \App\Models\User) {
                return null;
            }

            if ($user->account_status === AccountStatus::Approved) {
                return null;
            }

            // Ability swalayan yang tetap boleh dipakai saat akun belum disetujui.
            if (in_array($ability, self::SELF_SERVICE_ABILITIES, true)) {
                return null;
            }

            return false;
        });
    }

    /**
     * Ability yang tidak memerlukan status akun `approved`.
     * Sengaja tidak memuat ability operasional apa pun.
     *
     * @var array<int, string>
     */
    private const SELF_SERVICE_ABILITIES = [
        // Notifikasi milik sendiri
        'viewOwnNotification',
        'markNotificationAsRead',
        // Profil perusahaan bagi transportir yang masih mengisi pendaftaran
        'updateOwnTransporterProfile',
    ];
}
