<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kim_cards', function (Blueprint $table) {
            // Menandai KIM yang diterbitkan lewat jalur dispensasi (KIM sementara).
            $table->boolean('is_dispensation')->default(false)->after('image_path')->index();

            // Salinan batas dispensasi terawal agar currentStatus() tetap satu query.
            $table->date('dispensation_expires_at')->nullable()->after('is_dispensation');

            // Penanda audit saat sistem menandai dispensasi sudah terlampaui.
            $table->timestamp('dispensation_lapsed_at')->nullable()->after('dispensation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('kim_cards', function (Blueprint $table) {
            $table->dropIndex(['is_dispensation']);
            $table->dropColumn([
                'is_dispensation',
                'dispensation_expires_at',
                'dispensation_lapsed_at',
            ]);
        });
    }
};
