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
        if (! Schema::hasTable('movimientos_caja')) {
            Schema::create('movimientos_caja', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 20);
                $table->date('fecha');
                $table->string('concepto');
                $table->text('detalle')->nullable();
                $table->decimal('importe', 15, 2)->default(0);
                $table->string('origen', 30)->default('manual');
                $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->index(['tipo', 'fecha']);
                $table->index('origen');
                $table->index('venta_id');
            });

            return;
        }

        // La tabla ya existía en instalaciones previas (creada fuera de
        // migraciones): completar solo lo que falte, sin perder datos.
        Schema::table('movimientos_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos_caja', 'venta_id')) {
                $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            }
            if (! Schema::hasColumn('movimientos_caja', 'usuario_id')) {
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('movimientos_caja', 'detalle')) {
                $table->text('detalle')->nullable();
            }
            if (! Schema::hasColumn('movimientos_caja', 'observaciones')) {
                $table->text('observaciones')->nullable();
            }
            if (! Schema::hasColumn('movimientos_caja', 'origen')) {
                $table->string('origen', 30)->default('manual');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
