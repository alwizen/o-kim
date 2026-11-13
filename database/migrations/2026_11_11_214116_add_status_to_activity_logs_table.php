<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Hasil aksi: 'success' atau 'failed'. Kegagalan menyimpan pesan
            // error pada kolom description agar dapat ditelusuri belakangan.
            $table->string('status', 10)->default('success')->after('event')->index();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
