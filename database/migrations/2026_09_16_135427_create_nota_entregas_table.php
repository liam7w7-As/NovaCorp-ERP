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
        Schema::create('nota_entregas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->enum('estado', ['emitida', 'anulada'])->default('emitida');
            $table->string('cliente_nombre');
            $table->text('observaciones')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha']);
            $table->index(['venta_id', 'estado']);
        });

        Schema::create('detalle_nota_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_entrega_id')->constrained('nota_entregas')->cascadeOnDelete();
            $table->foreignId('detalle_venta_id')->nullable()->constrained('detalle_venta')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo_producto');
            $table->string('descripcion_producto');
            $table->decimal('cantidad', 10, 2);
            $table->timestamps();

            $table->index(['producto_id', 'nota_entrega_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_nota_entregas');
        Schema::dropIfExists('nota_entregas');
    }
};
