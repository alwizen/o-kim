<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Setting\SettingsService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'kim.validity_months' => '6',
            'kim.number_prefix' => 'KIM/FT-TGL',
            'kim.number_period_format' => 'Y/m',
            'kim.number_sequence_digits' => '4',
        ];

        foreach ($defaults as $key => $value) {
            // firstOrCreate agar nilai yang sudah diubah admin tidak tertimpa saat seeder diulang.
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => SettingsService::GROUP_KIM]
            );
        }

        app(SettingsService::class)->flush();
    }
}
