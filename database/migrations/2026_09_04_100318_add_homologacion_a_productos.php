<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Homologación SIN: código de producto del catálogo oficial y unidad de medida
            $table->string('codigo_sin', 20)->nullable()->after('unidad');
            $table->string('unidad_sin', 10)->nullable()->after('codigo_sin');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['codigo_sin', 'unidad_sin']);
        });
    }
};
