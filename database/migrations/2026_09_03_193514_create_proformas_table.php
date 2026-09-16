<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proformas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->date('fecha');
            $table->date('validez')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->enum('estado', ['borrador', 'enviada', 'aprobada', 'rechazada', 'vencida', 'convertida'])->default('borrador');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('nota')->nullable();
            $table->boolean('reserva_stock')->default(false);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            // Campos comerciales del original (proformas-crear.html)
            $table->string('contacto')->nullable();
            $table->string('telefono')->nullable();
            $table->string('tiempo_entrega')->nullable();
            $table->string('condiciones_pago')->nullable();
            $table->string('garantia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proformas');
    }
};
