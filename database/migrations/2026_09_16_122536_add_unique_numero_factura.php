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
        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->unique('numero_factura');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->dropUnique(['numero_factura']);
        });
    }
};
