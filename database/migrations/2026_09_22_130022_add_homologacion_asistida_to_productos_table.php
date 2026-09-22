<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('actividad_economica_sin', 20)->nullable()->after('codigo_sin');
            $table->string('homologacion_estado', 20)->default('pendiente')->after('unidad_sin');
            $table->unsignedTinyInteger('homologacion_confianza')->nullable()->after('homologacion_estado');
            $table->timestamp('homologado_at')->nullable()->after('homologacion_confianza');
            $table->foreignId('homologado_por')->nullable()->after('homologado_at')->constrained('users')->nullOnDelete();
        });

        DB::table('productos')
            ->whereNotNull('codigo_sin')
            ->where('codigo_sin', '<>', '')
            ->update([
                'homologacion_estado' => 'confirmada',
                'homologacion_confianza' => 100,
                'homologado_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('homologado_por');
            $table->dropColumn([
                'actividad_economica_sin',
                'homologacion_estado',
                'homologacion_confianza',
                'homologado_at',
            ]);
        });
    }
};
