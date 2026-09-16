<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_contingencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('codigo_evento'); // 1-7 según SIN
            $table->string('descripcion');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->enum('estado', ['abierto', 'cerrado', 'enviado', 'validado'])->default('abierto');
            $table->string('codigo_recepcion_evento')->nullable();
            $table->string('paquete_path')->nullable();
            $table->string('codigo_recepcion_paquete')->nullable();
            $table->enum('estado_paquete', ['pendiente', 'enviado', 'validado', 'observado'])->default('pendiente');
            $table->text('observaciones_sin')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_contingencia');
    }
};
