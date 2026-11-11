<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berita_acara_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kim_application_id')->unique()->constrained()->cascadeOnDelete();
            // Nomor BA mengikuti nomor pengajuan.
            $table->string('number', 100);
            $table->string('pdf_path');
            // SHA-256 berkas PDF beku (integritas dokumen).
            $table->string('pdf_hash', 64);
            // Dasar hukum tanda tangan elektronik (mis. UU ITE), disalin saat dokumen dibekukan.
            $table->text('legal_reference')->nullable();
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berita_acara_documents');
    }
};
