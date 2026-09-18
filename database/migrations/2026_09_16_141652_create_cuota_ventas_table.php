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
        Schema::create('cuota_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 10, 2);
            $table->decimal('pagado', 10, 2)->default(0);
            $table->enum('estado', ['pendiente', 'parcial', 'pagada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['venta_id', 'numero']);
            $table->index(['estado', 'fecha_vencimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuota_ventas');
    }
};
