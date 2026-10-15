<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_item_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_item_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_result_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_item_point_id')->constrained()->restrictOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->timestamps();

            $table->unique(['inspection_result_id', 'inspection_item_point_id'], 'inspection_result_point_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_result_points');
        Schema::dropIfExists('inspection_item_points');
    }
};
