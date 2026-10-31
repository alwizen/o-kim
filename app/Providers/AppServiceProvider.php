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
        $this->opaqueMobileChrome();
        $this->applyPressBluePalette();

        // Tautan "Lupa kata sandi?" pada halaman login panel admin.
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => Blade::render(<<<'BLADE'
                <div class="text-center">
                    <a href="{{ route('password.request') }}"
                       class="text-sm font-medium text-auth-600 underline-offset-4 hover:underline">
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
     * Kurangi transparansi sidebar dan topbar pada layar kecil.
     *
     * Tema Press (terutama Vellum) memang berniat menampilkan permukaan kaca
     * tembus pandang. Di layar kecil, konten yang lewat di belakang topbar dan
     * sidebar membuat label navigasi sulit dibaca, jadi di bawah breakpoint lg
     * permukaannya dibuat hampir pekat. Di desktop efek kacanya tetap utuh.
     */
    private function opaqueMobileChrome(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <style>
                    @media (max-width: 1023.98px) {
                        .fi-topbar {
                            background-color: color-mix(in oklab, var(--press-bg-app) 92%, oklch(1 0 0)) !important;
                            backdrop-filter: none !important;
                            -webkit-backdrop-filter: none !important;
                        }

                        .fi-sidebar {
                            background-color: color-mix(in oklab, var(--press-bg-app) 94%, oklch(1 0 0)) !important;
                            backdrop-filter: none !important;
                            -webkit-backdrop-filter: none !important;
                        }

                        /* Panel dropdown/modal ikut dibuat pekat agar teks tidak bertumpuk. */
                        .fi-dropdown-panel,
                        .fi-modal-window {
                            backdrop-filter: none !important;
                            -webkit-backdrop-filter: none !important;
                        }
                    }
                </style>
            HTML),
        );
    }

    /**
     * Terapkan color scheme biru pada tema Press (Broadsheet, Vellum, Telex).
     *
     * Tiap preset Press mendeklarasikan palet aksennya sendiri pada :root
     * (Broadsheet memakai merah koran). Menimpa variabel --press-primary-* dan
     * --press-neutral-* di :root membuat seluruh komponen panel ikut biru tanpa
     * menyentuh paket vendor. Nilai oklch di bawah ekuivalen dengan palet aplikasi:
     * #1C4D8D sebagai aksen utama, #F8F3EC tetap dipakai sebagai latar terang
     * namun digeser sedikit ke hue biru agar serasi.
     */
    private function applyPressBluePalette(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <style>
                    :root {
                        /* Aksen biru */
                        --press-primary-50: oklch(97.6% 0.009 242.8);
                        --press-primary-100: oklch(95.0% 0.019 235.7);
                        --press-primary-200: oklch(90.5% 0.048 218.1);
                        --press-primary-300: oklch(81.6% 0.075 228.8);
                        --press-primary-400: oklch(61.1% 0.112 249.1);
                        --press-primary-500: oklch(52.0% 0.119 252.4);
                        --press-primary-600: oklch(42.3% 0.118 256.6);
                        --press-primary-700: oklch(36.5% 0.097 256.1);
                        --press-primary-800: oklch(28.5% 0.086 260.8);
                        --press-primary-900: oklch(24.6% 0.071 260.7);
                        --press-primary-950: oklch(20.4% 0.054 260.9);

                        /* Netral bernuansa biru (menggantikan kertas hangat Broadsheet) */
                        --press-neutral-50: oklch(98.4% 0.005 240);
                        --press-neutral-100: oklch(96.6% 0.008 240);
                        --press-neutral-200: oklch(93.0% 0.012 244);
                        --press-neutral-300: oklch(88.0% 0.016 246);
                        --press-neutral-400: oklch(71.0% 0.022 250);
                        --press-neutral-500: oklch(55.5% 0.026 252);
                        --press-neutral-600: oklch(45.0% 0.028 254);
                        --press-neutral-700: oklch(37.5% 0.030 256);
                        --press-neutral-800: oklch(28.5% 0.028 258);
                        --press-neutral-900: oklch(22.0% 0.024 260);
                        --press-neutral-950: oklch(15.5% 0.020 262);

                        /* Latar terang mengikuti #F8F3EC yang digeser ke hue biru */
                        --press-bg-app: oklch(97.4% 0.008 240);
                        --press-bg-surface: oklch(99.2% 0.003 240);
                        --press-bg-topbar: oklch(99.2% 0.003 240);
                        --press-bg-sidebar: var(--press-bg-app);
                    }

                    /* ----- Dark mode ------------------------------------------------
                       Preset Press membawa latar gelapnya sendiri; nilai di bawah
                       menjaga hue tetap biru dan memastikan permukaan tetap terbaca
                       saat mode gelap aktif. */
                    .dark {
                        --press-primary-50: oklch(26.0% 0.030 260);
                        --press-primary-100: oklch(30.5% 0.038 259);
                        --press-primary-200: oklch(37.0% 0.052 258);
                        --press-primary-300: oklch(47.0% 0.070 257);
                        --press-primary-400: oklch(63.0% 0.098 255);
                        --press-primary-500: oklch(70.0% 0.104 253);
                        --press-primary-600: oklch(76.0% 0.098 252);
                        --press-primary-700: oklch(82.0% 0.080 250);
                        --press-primary-800: oklch(88.0% 0.060 248);
                        --press-primary-900: oklch(93.0% 0.036 246);
                        --press-primary-950: oklch(96.5% 0.018 244);

                        --press-neutral-50: oklch(97.0% 0.004 250);
                        --press-neutral-100: oklch(93.0% 0.006 250);
                        --press-neutral-200: oklch(86.0% 0.008 252);
                        --press-neutral-300: oklch(75.0% 0.010 254);
                        --press-neutral-400: oklch(64.0% 0.012 256);
                        --press-neutral-500: oklch(54.0% 0.014 258);
                        --press-neutral-600: oklch(44.0% 0.014 258);
                        --press-neutral-700: oklch(35.0% 0.014 260);
                        --press-neutral-800: oklch(27.0% 0.013 260);
                        --press-neutral-900: oklch(20.0% 0.012 262);
                        --press-neutral-950: oklch(14.0% 0.010 262);

                        --press-bg-app: oklch(19.5% 0.014 262);
                        --press-bg-surface: oklch(24.0% 0.014 262);
                        --press-bg-topbar: oklch(24.0% 0.014 262);
                        --press-bg-overlay: oklch(26.0% 0.014 262);
                        --press-bg-sidebar: oklch(21.5% 0.014 262);
                    }

                    /* ----- Menu aktif -------------------------------------------------
                       Broadsheet menandai item aktif dengan garis bawah saja sehingga
                       posisi halaman sulit dikenali. Item aktif diberi latar biru pekat
                       agar jelas, baik di sidebar maupun topbar (navigasi atas). */
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
                        background-color: var(--press-primary-100);
                        color: var(--press-primary-900);
                        box-shadow: inset 0 0 0 1px var(--press-primary-300);
                    }

                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon,
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label {
                        color: var(--press-primary-900);
                        font-weight: 650;
                    }

                    /* Garis penanda di sisi kiri item aktif. */
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
                        position: relative;
                    }

                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::before {
                        content: '';
                        position: absolute;
                        inset-block: 0.25rem;
                        inset-inline-start: 0;
                        width: 3px;
                        border-radius: 9999px;
                        background-color: var(--press-primary-600);
                    }

                    /* Dark mode: latar biru pekat, teks dan ikon terang agar kontras.
                       Skala primary dibalik pada mode gelap, jadi dipakai nilai eksplisit. */
                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
                        background-color: oklch(38.0% 0.085 257);
                        color: oklch(98.0% 0.006 250);
                        box-shadow: inset 0 0 0 1px oklch(52.0% 0.100 255);
                    }

                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon,
                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label {
                        color: oklch(98.0% 0.006 250);
                    }

                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::before {
                        background-color: oklch(84.0% 0.070 250);
                    }

                    /* Navigasi atas (topbar). */
                    .fi-body-has-top-navigation .fi-topbar-item.fi-active .fi-topbar-item-btn {
                        background-color: var(--press-primary-100);
                        color: var(--press-primary-900);
                        box-shadow: inset 0 0 0 1px var(--press-primary-300);
                    }

                    .fi-body-has-top-navigation .fi-topbar-item.fi-active .fi-topbar-item-btn .fi-icon,
                    .fi-body-has-top-navigation .fi-topbar-item.fi-active .fi-topbar-item-btn .fi-topbar-item-label {
                        color: var(--press-primary-900);
                    }

                    .dark .fi-body-has-top-navigation .fi-topbar-item.fi-active .fi-topbar-item-btn {
                        background-color: oklch(38.0% 0.085 257);
                        color: oklch(98.0% 0.006 250);
                        box-shadow: inset 0 0 0 1px oklch(52.0% 0.100 255);
                    }

                    .dark .fi-body-has-top-navigation .fi-topbar-item.fi-active .fi-topbar-item-btn .fi-icon,
                    .dark .fi-body-has-top-navigation .fi-topbar-item.fi-active .fi-topbar-item-btn .fi-topbar-item-label {
                        color: oklch(98.0% 0.006 250);
                    }

                    /* ----- Badge jumlah notifikasi (ikon lonceng) ---------------------
                       Badge bawaan memakai latar gray transparan dengan teks abu-abu,
                       sehingga nyaris tak terbaca di atas topbar gelap. Angka notifikasi
                       adalah penanda penting, jadi dibuat pekat di kedua mode. */
                    .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge {
                        background-color: #b03626;
                        color: #fff;
                        box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.12);
                        font-weight: 600;
                    }

                    .dark .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge {
                        background-color: #e0523c;
                        color: #fff;
                        box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.22);
                    }

                    /* Cincin di sekeliling badge agar terpisah dari topbar. */
                    .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn {
                        background-color: var(--press-bg-topbar);
                    }
                </style>
            HTML),
        );
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
