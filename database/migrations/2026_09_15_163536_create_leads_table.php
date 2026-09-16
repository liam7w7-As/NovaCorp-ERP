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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etapa_crm_id')->constrained('etapas_crm')->restrictOnDelete();
            $table->foreignId('canal_whatsapp_id')->nullable()->constrained('canal_whatsapps')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->string('telefono', 30);
            $table->string('telefono_normalizado', 30)->index();
            $table->string('correo')->nullable();
            $table->string('empresa')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('origen', 40)->default('manual');
            $table->enum('tipo_consulta', ['informacion', 'precio', 'otro'])->nullable();
            $table->decimal('valor_estimado', 12, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamp('ultima_interaccion_at')->nullable();
            $table->timestamp('etapa_actualizada_at')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->string('motivo_perdida')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['etapa_crm_id', 'vendedor_id']);
            $table->index(['canal_whatsapp_id', 'vendedor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
