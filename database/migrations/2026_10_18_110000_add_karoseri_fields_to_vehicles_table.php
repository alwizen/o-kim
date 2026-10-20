<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('karoseri_builder', 100)->nullable()->after('brand');
            $table->string('karoseri_built_period', 20)->nullable()->after('karoseri_builder');
            $table->decimal('tank_wall_thickness_mm', 6, 2)->nullable()->after('tank_material');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['karoseri_builder', 'karoseri_built_period', 'tank_wall_thickness_mm']);
        });
    }
};
