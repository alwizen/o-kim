<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_scan_logs', function (Blueprint $table) {
            $table->id();
            // Jenis dokumen yang diverifikasi: 'ba' (Berita Acara) atau 'kim'.
            $table->string('document_type', 10)->index();

            // Terisi bila QR tanda tangan elektronik dipindai (BA/KIM).
            $table->foreignId('document_signature_id')->nullable()->constrained()->cascadeOnDelete();
            // Terisi bila QR validasi kartu KIM dipindai.
            $table->foreignId('kim_card_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_scan_logs');
    }
};
