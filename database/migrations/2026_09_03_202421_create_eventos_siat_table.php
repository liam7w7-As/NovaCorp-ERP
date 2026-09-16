<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_siat', function (Blueprint $table) {
            $table->id();
            $table->string('metodo');
            $table->text('parametros')->nullable();
            $table->text('respuesta')->nullable();
            $table->boolean('exitoso')->default(false);
            $table->dateTime('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_siat');
    }
};
