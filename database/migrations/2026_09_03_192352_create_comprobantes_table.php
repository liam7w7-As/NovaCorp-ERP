<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->string('concepto');
            $table->string('entidad')->nullable();
            $table->decimal('monto', 10, 2);
            $table->text('nota')->nullable();
            $table->date('fecha');
            $table->string('hora', 5)->nullable();
            $table->string('metodo')->default('Efectivo');
            $table->string('referencia')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('origen_compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('origen_venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
