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
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->string('mime_type')->nullable()->after('archivo_url');
            $table->string('nombre_archivo')->nullable()->after('mime_type');
            $table->unsignedBigInteger('tamano_archivo')->nullable()->after('nombre_archivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->dropColumn(['mime_type', 'nombre_archivo', 'tamano_archivo']);
        });
    }
};
