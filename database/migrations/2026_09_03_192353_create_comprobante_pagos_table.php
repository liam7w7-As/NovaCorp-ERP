<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->enum('forma_pago', ['efectivo', 'transferencia', 'qr', 'tarjeta', 'cheque', 'otro'])->default('efectivo');
            $table->string('banco')->nullable();
            $table->string('cuenta')->nullable();
            $table->string('referencia')->nullable();
            $table->text('nota')->nullable();
            $table->decimal('monto', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_pagos');
    }
};
