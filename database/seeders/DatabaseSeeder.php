<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder yang dijalankan pada `migrate:fresh --seed`.
     *
     * Catatan: DummyDataSeeder sengaja TIDAK didaftarkan di sini agar data
     * dummy tidak ikut terbuat. Jalankan terpisah bila diperlukan:
     *
     *     php artisan db:seed --class=DummyDataSeeder
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SettingSeeder::class,
            NotificationSettingSeeder::class,
            DocumentTypeSeeder::class,
            InspectionChecklistSeeder::class,
        ]);
    }
}
