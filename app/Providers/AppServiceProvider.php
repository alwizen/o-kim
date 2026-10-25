<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
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
}
