<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminStatsOverview;
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
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
            ->brandlogo(asset('img/v3.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('img/fav.svg'))
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->maxContentWidth(Width::Full)
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AdminStatsOverview::class,
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
                PressFilamentTheme::make()->runtimeSwitch([PressVariant::Broadsheet, PressVariant::Vellum])->rail(false)
            )
            ->font(
                'Inter',
                url: asset('fonts/filament/filament/inter/index.css')
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
