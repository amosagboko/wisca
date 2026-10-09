<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_periodic_data', function (Blueprint $table) {
            $table->string('measure_key', 32)->nullable()->after('kpi_id');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_periodic_data', function (Blueprint $table) {
            $table->dropColumn('measure_key');
        });
    }
};
