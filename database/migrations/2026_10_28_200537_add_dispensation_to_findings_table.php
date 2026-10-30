<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            // Batas waktu perbaikan yang ditetapkan FTM (wajib saat dispensasi).
            $table->date('dispensation_due_date')->nullable()->after('closed_at');

            // Alasan pemberian dispensasi (wajib saat dispensasi).
            $table->text('dispensation_reason')->nullable()->after('dispensation_due_date');

            // Penanggung jawab dispensasi (hanya FTM).
            $table->foreignId('dispensated_by')->nullable()->after('dispensation_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('dispensated_at')->nullable()->after('dispensated_by');

            $table->index(['status', 'dispensation_due_date'], 'findings_status_dispensation_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropIndex('findings_status_dispensation_due_index');
            $table->dropConstrainedForeignId('dispensated_by');
            $table->dropColumn([
                'dispensation_due_date',
                'dispensation_reason',
                'dispensated_at',
            ]);
        });
    }
};
