<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('chassis_number', 100)->nullable()->after('tank_capacity');
            $table->string('engine_number', 100)->nullable()->after('chassis_number');
            $table->string('transport_type', 100)->nullable()->after('engine_number');
            $table->string('tank_material', 100)->nullable()->after('transport_type');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'chassis_number',
                'engine_number',
                'transport_type',
                'tank_material',
            ]);
        });
    }
};
