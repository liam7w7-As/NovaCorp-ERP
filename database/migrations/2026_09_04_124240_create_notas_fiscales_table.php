<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas_electronicas')->cascadeOnDelete();
            $table->enum('tipo', ['debito', 'credito']);
            $table->string('numero')->unique();
            $table->decimal('monto', 10, 2);
            $table->string('motivo');
            $table->enum('estado', ['emitida', 'anulada'])->default('emitida');
            $table->string('cuf')->nullable();
            $table->string('codigo_recepcion')->nullable();
            $table->boolean('simulada')->default(false);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_fiscales');
    }
};
