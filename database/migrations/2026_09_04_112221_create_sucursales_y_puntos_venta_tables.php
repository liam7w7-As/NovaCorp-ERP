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
        if (! Schema::hasTable('sucursales')) {
            Schema::create('sucursales', function (Blueprint $table) {
                $table->id();
                $table->integer('codigo')->default(0)->unique();
                $table->string('nombre', 150);
                $table->string('direccion', 255)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('municipio', 100)->default('Santa Cruz de la Sierra');
                $table->boolean('activa')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('puntos_venta')) {
            Schema::create('puntos_venta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->integer('codigo')->default(0);
                $table->string('nombre', 150);
                $table->string('tipo_punto_venta', 100)->default('Punto de Venta Fijo');
                $table->string('cuis', 100)->nullable();
                $table->dateTime('cuis_vigencia')->nullable();
                $table->text('cufd')->nullable();
                $table->string('codigo_control', 100)->nullable();
                $table->dateTime('cufd_vigencia')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['sucursal_id', 'codigo']);
            });
        }

        if (! Schema::hasColumn('users', 'sucursal_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('ventas', 'sucursal_id')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
                $table->integer('codigo_sucursal')->default(0);
                $table->integer('codigo_punto_venta')->default(0);
            });
        }

        if (! Schema::hasColumn('facturas_electronicas', 'sucursal_id')) {
            Schema::table('facturas_electronicas', function (Blueprint $table) {
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
                $table->integer('codigo_sucursal')->default(0);
                $table->integer('codigo_punto_venta')->default(0);
            });
        }

        if (! Schema::hasColumn('eventos_contingencia', 'sucursal_id')) {
            Schema::table('eventos_contingencia', function (Blueprint $table) {
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('punto_venta_id')->nullable()->constrained('puntos_venta')->nullOnDelete();
                $table->integer('codigo_sucursal')->default(0);
                $table->integer('codigo_punto_venta')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('eventos_contingencia', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['punto_venta_id']);
            $table->dropColumn(['sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta']);
        });

        Schema::table('facturas_electronicas', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['punto_venta_id']);
            $table->dropColumn(['sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['punto_venta_id']);
            $table->dropColumn(['sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['punto_venta_id']);
            $table->dropColumn(['sucursal_id', 'punto_venta_id']);
        });

        Schema::dropIfExists('puntos_venta');
        Schema::dropIfExists('sucursales');
    }
};
