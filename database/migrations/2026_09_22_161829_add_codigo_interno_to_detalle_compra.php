<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_compra', function (Blueprint $table) {
            $table->string('codigo_interno', 100)->nullable()->after('codigo_producto');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compra', function (Blueprint $table) {
            $table->dropColumn('codigo_interno');
        });
    }
};
