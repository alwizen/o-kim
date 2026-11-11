<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\ApplicationProgressStepper;
use App\Filament\Widgets\ApplicationTrendChart;
use App\Filament\Widgets\ExpiringDispensations;
use App\Filament\Widgets\ExpiringDocumentsTable;
use App\Filament\Widgets\FindingTrendChart;
use App\Filament\Widgets\FtmDecisionQueue;
use App\Filament\Widgets\FtmPendingDispensations;
use App\Filament\Widgets\FtmStatsOverview;
use App\Filament\Widgets\InspectionsDueToday;
use App\Filament\Widgets\PendingDocumentVerification;
use App\Filament\Widgets\PengawasApprovalQueue;
use App\Filament\Widgets\PengawasStatsOverview;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\TadInspectionQueue;
use App\Filament\Widgets\TadStatsOverview;
use Elemind\PressFilamentTheme\Enums\PressVariant;
use Elemind\PressFilamentTheme\PressFilamentTheme;
use Filament\FontProviders\BunnyFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandlogo(asset('img/ppn.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/fav.svg'))
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->unsavedChangesAlerts()
            // ->darkMode(false)
            ->maxContentWidth(Width::Full)
            ->login(Login::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            // Urutan grup menu; grup di luar daftar ini tampil setelahnya.
            ->navigationGroups([
                NavigationGroup::make('Operasional'),
                NavigationGroup::make('Master Data'),
                NavigationGroup::make('User Manajemen'),
                NavigationGroup::make('Data & Profil'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AdminStatsOverview::class,
                ApplicationProgressStepper::class,
                TadStatsOverview::class,
                PengawasStatsOverview::class,
                FtmStatsOverview::class,
                TadInspectionQueue::class,
                PengawasApprovalQueue::class,
                FtmDecisionQueue::class,
                PendingDocumentVerification::class,
                ExpiringDispensations::class,
                InspectionsDueToday::class,
                ExpiringDocumentsTable::class,
                FtmPendingDispensations::class,
                ApplicationTrendChart::class,
                FindingTrendChart::class,
                RecentActivity::class,
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(
                PressFilamentTheme::make()->runtimeSwitch([PressVariant::Broadsheet, PressVariant::Vellum, PressVariant::Telex])->rail(false)
            )
            /**
             * Font sans ditentukan setelah plugin di-boot.
             *
             * PressFilamentTheme::boot() memanggil registerFonts() yang menimpa apa pun
             * yang di-set lewat ->font() pada rantai panel(), karena Panel::boot()
             * menjalankan plugin setelah rantai itu selesai. bootUsing() dieksekusi
             * paling akhir, sehingga nilai di sini yang berlaku.
             *
             * Wajib berupa Closure: plugin memakai runtimeSwitch, jadi varian baru
             * diketahui saat render (dari cookie), bukan saat panel dikonfigurasi.
             */
            ->bootUsing(function (Panel $panel): void {
                $plugin = $panel->getPlugin('press-filament-theme');

                $isBroadsheet = fn (): bool => $plugin->resolveVariant() === PressVariant::Broadsheet;

                $panel->font(
                    fn (): string => $isBroadsheet()
                        ? 'Figtree'
                        : $plugin->resolveVariant()->getSansFont(),
                    provider: BunnyFontProvider::class,
                );

                // Broadsheet memakai Inter untuk heading (pengganti Instrument Serif).
                // Varian lain memakai serif yang ditetapkan plugin; 'ui-serif' dipakai
                // sebagai cadangan karena BunnyFontProvider menolak family null.
                $panel->serifFont(
                    fn (): string => $isBroadsheet()
                        ? 'Inter'
                        : ($plugin->resolveVariant()->getSerifFont() ?? 'ui-serif'),
                    url: fn (): ?string => $isBroadsheet()
                        ? null
                        : $plugin->resolveVariant()->getSerifFontUrl(),
                    provider: BunnyFontProvider::class,
                );
            })
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
