<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->unsignedTinyInteger('tipo_emision')->default(1)->after('simulada');
            $table->string('cafc')->nullable()->after('tipo_emision');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->dropColumn(['tipo_emision', 'cafc']);
        });
    }
};
