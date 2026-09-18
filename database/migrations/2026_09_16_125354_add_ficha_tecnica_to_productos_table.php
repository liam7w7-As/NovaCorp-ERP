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
        Schema::table('productos', function (Blueprint $table) {
            $table->string('ficha_tecnica_path')->nullable()->after('stock_min');
            $table->string('ficha_tecnica_nombre')->nullable()->after('ficha_tecnica_path');
            $table->string('ficha_tecnica_mime')->nullable()->after('ficha_tecnica_nombre');
            $table->unsignedBigInteger('ficha_tecnica_tamano')->nullable()->after('ficha_tecnica_mime');
            $table->json('imagenes')->nullable()->after('ficha_tecnica_tamano');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'ficha_tecnica_path',
                'ficha_tecnica_nombre',
                'ficha_tecnica_mime',
                'ficha_tecnica_tamano',
                'imagenes',
            ]);
        });
    }
};
