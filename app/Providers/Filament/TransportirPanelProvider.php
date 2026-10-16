<?php

namespace App\Providers\Filament;

use App\Filament\Transportir\Widgets\TransportirExpiringDocumentsTable;
use App\Filament\Transportir\Widgets\TransportirStatsOverview;
use Elemind\PressFilamentTheme\PressFilamentTheme;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
             ->brandlogo(asset('img/logo.png'))
            ->brandLogoHeight('3rem')
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
                PressFilamentTheme::make()->vellum()
            )
            ->discoverWidgets(in: app_path('Filament/Transportir/Widgets'), for: 'App\Filament\Transportir\Widgets')
            ->widgets([
                TransportirStatsOverview::class,
                // AccountWidget::class,
                TransportirExpiringDocumentsTable::class,
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
