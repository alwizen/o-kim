<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kim_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_result_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department', 15)->index();
            $table->text('description');
            $table->string('severity', 20)->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('finding_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('finding_action_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_action_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->timestamps();
        });

        Schema::create('finding_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_action_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 15);
            $table->text('notes')->nullable();
            $table->timestamp('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_verifications');
        Schema::dropIfExists('finding_action_attachments');
        Schema::dropIfExists('finding_actions');
        Schema::dropIfExists('findings');
    }
};
