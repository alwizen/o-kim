<?php

namespace Database\Seeders;

use App\Services\Notification\NotificationSettingsService;
use Illuminate\Database\Seeder;

class NotificationSettingSeeder extends Seeder
{
    public function run(): void
    {
        app(NotificationSettingsService::class)->seedDefaults();
    }
}
