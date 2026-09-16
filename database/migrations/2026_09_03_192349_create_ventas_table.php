<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->enum('tipo', ['con_factura', 'sin_factura'])->default('sin_factura');
            $table->enum('modalidad', ['contado', 'credito'])->default('contado');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->date('fecha');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('base_df', 10, 2)->nullable();
            $table->decimal('debito_fiscal', 10, 2)->nullable();
            $table->enum('estado', ['activa', 'anulada'])->default('activa');
            $table->text('observaciones')->nullable();
            $table->boolean('origen_siat')->default(false);
            $table->string('codigo_autorizacion')->nullable();
            $table->string('numero_factura_siat')->nullable();
            $table->string('nit_cliente')->nullable();
            $table->string('comprobante_numero')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
