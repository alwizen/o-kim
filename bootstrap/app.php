<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Akun yang belum disetujui tidak boleh menyentuh rute operasional web.
        $middleware->alias([
            'account.approved' => \App\Http\Middleware\EnsureAccountIsApproved::class,
        ]);

        // User yang sudah login diarahkan ke halaman sesuai status akunnya,
        // bukan selalu ke halaman utama.
        $middleware->redirectUsersTo(function (Request $request) {
            return auth()->user()?->homeUrl() ?? '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
