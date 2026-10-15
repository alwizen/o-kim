<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kim_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspected_by')->constrained('users')->restrictOnDelete();
            $table->string('result', 20);
            $table->text('notes')->nullable();
            $table->timestamp('inspected_at');
            $table->timestamps();

            $table->unique(['kim_application_id', 'inspection_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
