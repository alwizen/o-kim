<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_result_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->timestamps();

            $table->index('inspection_result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_result_attachments');
    }
};
