<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_electronicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('cuf')->unique()->nullable();
            $table->string('cufd')->nullable();
            $table->string('numero_factura');
            $table->enum('estado', ['emitida', 'anulada', 'rechazada', 'pendiente', 'observada'])->default('pendiente');
            $table->dateTime('fecha_emision');
            $table->text('xml_firmado')->nullable();
            $table->text('xml_respuesta')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('codigo_recepcion')->nullable();
            $table->string('transaccion', 10)->nullable();
            $table->text('leyenda')->nullable();
            $table->text('observaciones_sin')->nullable();
            $table->boolean('simulada')->default(false);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_electronicas');
    }
};
