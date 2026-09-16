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
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('canal_whatsapp_id')->nullable()->constrained('canal_whatsapps')->nullOnDelete();
            $table->foreignId('enviado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_message_id')->nullable()->unique();
            $table->enum('direccion', ['entrante', 'saliente']);
            $table->enum('tipo', ['texto', 'imagen', 'documento', 'audio', 'video', 'interactivo'])->default('texto');
            $table->text('contenido')->nullable();
            $table->string('archivo_url')->nullable();
            $table->enum('estado', ['recibido', 'pendiente', 'enviado', 'entregado', 'leido', 'error', 'simulado']);
            $table->timestamp('ocurrio_at');
            $table->timestamps();

            $table->index(['lead_id', 'ocurrio_at']);
            $table->index(['estado', 'ocurrio_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
