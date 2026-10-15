<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kim_application_id')->constrained()->cascadeOnDelete();
            $table->string('department', 15);
            $table->string('decision', 15);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['kim_application_id', 'department']);
        });

        Schema::create('kim_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kim_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kim_number', 30)->unique();
            $table->timestamp('issued_at');
            $table->date('expires_at');
            $table->string('qr_token', 64)->unique();
            $table->string('pdf_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kim_cards');
        Schema::dropIfExists('approvals');
    }
};
