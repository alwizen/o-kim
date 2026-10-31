<?php

namespace App\Providers\Filament;

use App\Filament\Transportir\Widgets\MyDispensationRequests;
use App\Filament\Transportir\Widgets\RejectedFollowUps;
use App\Filament\Transportir\Widgets\TransportirExpiringDocumentsTable;
use App\Filament\Transportir\Widgets\TransportirStatsOverview;
use App\Filament\Transportir\Widgets\VehicleProgressStepper;
use App\Filament\Widgets\RecentActivity;
use Elemind\PressFilamentTheme\Enums\PressVariant;
use Elemind\PressFilamentTheme\PressFilamentTheme;
use Filament\FontProviders\BunnyFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TransportirPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('transportir')
            ->path('transportir')
            ->brandlogo(asset('img/ppn.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/fav.svg'))
            ->databaseNotifications()
            ->globalSearch()
            ->colors([
                'primary' => Color::Green,
            ])
            ->discoverResources(in: app_path('Filament/Transportir/Resources'), for: 'App\Filament\Transportir\Resources')
            ->discoverPages(in: app_path('Filament/Transportir/Pages'), for: 'App\Filament\Transportir\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->plugin(
                PressFilamentTheme::make()->runtimeSwitch([PressVariant::Broadsheet, PressVariant::Vellum])->rail(false)
            )
            /**
             * Lihat catatan pada AdminPanelProvider: plugin mengeset font di boot(),
             * jadi nilai final harus ditentukan lewat bootUsing().
             * Broadsheet memakai Figtree (sans) dan Inter (heading), bukan Instrument Sans/Serif.
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

                // Hanya Broadsheet yang punya serif; varian lain dibiarkan apa adanya
                // karena BunnyFontProvider menolak family null.
                // Nilai harus berupa Closure agar mengikuti varian saat render;
                // varian non-Broadsheet memakai serif yang ditetapkan plugin.
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
            ->discoverWidgets(in: app_path('Filament/Transportir/Widgets'), for: 'App\Filament\Transportir\Widgets')
            ->widgets([
                TransportirStatsOverview::class,
                VehicleProgressStepper::class,
                RejectedFollowUps::class,
                MyDispensationRequests::class,
                TransportirExpiringDocumentsTable::class,
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
