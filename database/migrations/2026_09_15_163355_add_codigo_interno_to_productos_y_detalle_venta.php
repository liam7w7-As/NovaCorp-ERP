<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'codigo_interno')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->string('codigo_interno', 50)->nullable()->unique()->after('id');
            });
        }

        if (! Schema::hasColumn('detalle_venta', 'codigo_interno')) {
            Schema::table('detalle_venta', function (Blueprint $table) {
                $table->string('codigo_interno', 100)->nullable()->after('codigo_producto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'codigo_interno')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('codigo_interno');
            });
        }

        if (Schema::hasColumn('detalle_venta', 'codigo_interno')) {
            Schema::table('detalle_venta', function (Blueprint $table) {
                $table->dropColumn('codigo_interno');
            });
        }
    }
};
