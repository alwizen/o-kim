<?php

use App\Console\Commands\ExpireDispensationCards;
use App\Console\Commands\NotifyExpiringDocuments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat harian dokumen & KIM yang akan kedaluwarsa.
Schedule::command(NotifyExpiringDocuments::class)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

// KIM sementara yang batas dispensasinya terlewati otomatis tidak berlaku.
Schedule::command(ExpireDispensationCards::class)
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();
