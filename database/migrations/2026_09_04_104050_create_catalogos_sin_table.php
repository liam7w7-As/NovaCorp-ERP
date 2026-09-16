<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogos_sin', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40); // actividad, producto, documento, pago, leyenda, motivo, evento
            $table->string('codigo', 40);
            $table->string('descripcion');
            $table->text('extra')->nullable();
            $table->timestamps();
            $table->unique(['tipo', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogos_sin');
    }
};
