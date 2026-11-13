<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Pelaku aksi. Null untuk aktivitas tamu (mis. percobaan login gagal).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Salinan identitas saat aksi terjadi agar tetap historis bila akun
            // dihapus atau role-nya berubah.
            $table->string('user_name', 100);
            $table->string('user_role', 100)->nullable();

            // Jenis aksi: login, logout, approval.approved, kim.issued, dst.
            $table->string('event', 50)->index();

            // Objek terkait (polymorphic), mis. pengajuan KIM atau dokumen kendaraan.
            $table->nullableMorphs('subject');
            $table->string('subject_label', 150)->nullable();

            $table->text('description');
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
