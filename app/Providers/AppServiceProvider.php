<?php

namespace App\Providers;

use App\Enums\AccountStatus;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
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
        $this->applyBroadsheetHeadingFont();
        $this->customizeSidebarToggleIcons();

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
     * Ganti serif Broadsheet (Instrument Serif) menjadi Inter.
     *
     * Preset Broadsheet mengunci nama face-nya di CSS, bukan di panel provider:
     *
     *   --press-font-serif: 'Instrument Serif', ui-serif, Georgia, serif;  /* :57 *\/
     *   --press-font-heading: var(--press-font-serif);                     /* :59 *\/
     *
     * Heading memakai `var(--press-font-serif)` langsung, sehingga `->serifFont()`
     * pada panel provider tidak berpengaruh. Nilai variabelnya harus ditimpa di CSS.
     *
     * Hook didaftarkan lewat FilamentView (bukan `Panel::renderHook()`): Panel::boot()
     * menyalin render hook ke FilamentView sebelum plugin di-boot, jadi hook yang
     * didaftarkan dari dalam plugin/bootUsing() tidak akan pernah ter-render.
     * FilamentView::registerRenderHook() meng-append, sehingga aturan ini tampil
     * paling akhir dan menang atas stylesheet preset.
     */
    private function applyBroadsheetHeadingFont(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <style>
                    :root {
                        --press-font-serif: 'Inter', ui-sans-serif, system-ui, sans-serif;
                    }
                </style>
            HTML),
        );
    }

    /**
     * Ganti ikon tombol buka/tutup sidebar (di samping logo) dari chevron ke bars-3.
     *
     * Chevron kiri/kanan mudah disalahartikan sebagai tombol "kembali" / "maju",
     * padahal fungsinya membuka dan menutup sidebar. Ikon bars-3 tidak
     * menyiratkan navigasi sehingga maksudnya lebih jelas.
     *
     * Alias diambil dari Filament\View\PanelsIconAlias agar tidak bergantung pada
     * string mentah; view asalnya:
     * vendor/filament/filament/resources/views/livewire/sidebar.blade.php.
     */
    private function customizeSidebarToggleIcons(): void
    {
        FilamentIcon::register([
            // Sidebar tertutup — tombol membuka sidebar.
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => Heroicon::OutlinedBars3BottomRight,
            // Sidebar terbuka — tombol menutup sidebar.
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => Heroicon::OutlinedBars3BottomLeft,
        ]);
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
