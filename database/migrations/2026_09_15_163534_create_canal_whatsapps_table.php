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
        Schema::create('canal_whatsapps', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ciudad')->nullable();
            $table->string('telefono', 30);
            $table->string('telefono_normalizado', 30)->unique();
            $table->string('waba_id')->nullable()->index();
            $table->string('phone_number_id')->nullable()->unique();
            $table->enum('estado', ['pendiente', 'conectado', 'inactivo', 'error'])->default('pendiente');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('canal_whatsapp_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canal_whatsapp_id')->constrained('canal_whatsapps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['canal_whatsapp_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canal_whatsapp_user');
        Schema::dropIfExists('canal_whatsapps');
    }
};
