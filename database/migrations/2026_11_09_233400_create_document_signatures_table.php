<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            // Dokumen pemilik tanda tangan: Berita Acara atau Kartu KIM.
            $table->morphs('signable');
            $table->string('department', 15);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Salinan identitas saat dokumen dibekukan (agar tetap historis).
            $table->string('name');
            $table->string('jabatan', 100)->nullable();
            $table->timestamp('decided_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            // Token unik untuk QR verifikasi tanda tangan.
            $table->string('qr_token', 64)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['signable_type', 'signable_id', 'sort_order'], 'document_signatures_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
