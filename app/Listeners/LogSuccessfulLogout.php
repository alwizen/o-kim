<?php

namespace App\Listeners;

use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Events\Logout;

/**
 * Catat logout dari jalur mana pun, termasuk logout panel Filament yang tidak
 * melewati LoginController.
 */
class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if ($user === null) {
            return;
        }

        // Satu-satunya tempat logout dicatat. LoginController::destroy dan logout
        // panel Filament sama-sama memicu event Logout, jadi mencatat di sini
        // menjamin tidak ada duplikat.
        app(ActivityLogger::class)->log(
            'logout',
            $user,
            $user->name.' keluar dari sistem.',
            [],
            label: $user->email,
        );
    }
}
