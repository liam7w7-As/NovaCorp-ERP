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
        Schema::create('etapas_crm', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('orden');
            $table->string('color', 20)->default('#64748B');
            $table->enum('tipo', ['activa', 'ganada', 'perdida'])->default('activa');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['activa', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etapas_crm');
    }
};
