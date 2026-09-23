<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ventas', 'compras', 'proformas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('descuento_tipo', 10)->default('fijo')->after('descuento');
            });
        }
    }

    public function down(): void
    {
        foreach (['ventas', 'compras', 'proformas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('descuento_tipo');
            });
        }
    }
};
