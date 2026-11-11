<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kim_cards', function (Blueprint $table) {
            // SHA-256 berkas PDF kartu KIM final (integritas dokumen).
            $table->string('pdf_hash', 64)->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('kim_cards', function (Blueprint $table) {
            $table->dropColumn('pdf_hash');
        });
    }
};
