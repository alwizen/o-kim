<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kim_applications', function (Blueprint $table) {
            $table->timestamp('inspection_scheduled_at')->nullable()->after('submitted_by');
            $table->foreignId('inspection_scheduled_by')->nullable()->after('inspection_scheduled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kim_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspection_scheduled_by');
            $table->dropColumn('inspection_scheduled_at');
        });
    }
};
