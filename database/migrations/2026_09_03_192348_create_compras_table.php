<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->enum('tipo', ['con_factura', 'sin_factura'])->default('con_factura');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('proveedor_nombre');
            $table->date('fecha');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('base_cf', 10, 2)->nullable();
            $table->decimal('credito_fiscal', 10, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('origen_siat')->default(false);
            $table->string('codigo_autorizacion')->nullable();
            $table->string('numero_factura_siat')->nullable();
            $table->string('nit_proveedor')->nullable();
            $table->string('comprobante_numero')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
