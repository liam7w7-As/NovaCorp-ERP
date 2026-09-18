<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock_reservado', 10, 2)->default(0)->after('stock');
        });

        Schema::table('detalle_venta', function (Blueprint $table) {
            $table->decimal('cantidad_entregada', 10, 2)->default(0)->after('cantidad');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->enum('entrega_estado', ['pendiente', 'parcial', 'entregada'])
                ->default('pendiente')
                ->after('estado');
            $table->timestamp('entregado_at')->nullable()->after('entrega_estado');
        });

        DB::table('ventas')
            ->where('estado', 'activa')
            ->where('origen_siat', false)
            ->orderBy('id')
            ->pluck('id')
            ->chunk(500)
            ->each(function ($ids): void {
                DB::table('detalle_venta')
                    ->whereIn('venta_id', $ids->all())
                    ->update(['cantidad_entregada' => DB::raw('cantidad')]);

                DB::table('ventas')
                    ->whereIn('id', $ids->all())
                    ->update([
                        'entrega_estado' => 'entregada',
                        'entregado_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['entrega_estado', 'entregado_at']);
        });

        Schema::table('detalle_venta', function (Blueprint $table) {
            $table->dropColumn('cantidad_entregada');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('stock_reservado');
        });
    }
};
