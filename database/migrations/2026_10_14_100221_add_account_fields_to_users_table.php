<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('phone', 30)->nullable()->after('google_id');
            $table->string('account_status', 20)->default('pending')->index()->after('phone');
            $table->text('rejection_reason')->nullable()->after('account_status');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'google_id',
                'phone',
                'account_status',
                'rejection_reason',
                'reviewed_at',
            ]);
        });
    }
};
