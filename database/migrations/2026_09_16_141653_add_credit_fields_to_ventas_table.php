<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedSmallInteger('credito_dias')->nullable()->after('modalidad');
            $table->unsignedSmallInteger('credito_cuotas')->default(1)->after('credito_dias');
            $table->date('fecha_vencimiento')->nullable()->after('fecha');
        });

        $ahora = now();
        DB::table('ventas')
            ->where('modalidad', 'credito')
            ->where('estado', 'activa')
            ->orderBy('id')
            ->get(['id', 'fecha', 'total', 'pagado'])
            ->each(function (object $venta) use ($ahora): void {
                $vencimiento = Carbon::parse($venta->fecha)->addDays(30)->toDateString();
                DB::table('ventas')
                    ->where('id', $venta->id)
                    ->update([
                        'credito_dias' => 30,
                        'credito_cuotas' => 1,
                        'fecha_vencimiento' => $vencimiento,
                    ]);

                $saldo = round((float) $venta->total - (float) $venta->pagado, 2);
                if ($saldo > 0 && ! DB::table('cuota_ventas')->where('venta_id', $venta->id)->exists()) {
                    DB::table('cuota_ventas')->insert([
                        'venta_id' => $venta->id,
                        'numero' => 1,
                        'fecha_vencimiento' => $vencimiento,
                        'monto' => $saldo,
                        'pagado' => 0,
                        'estado' => 'pendiente',
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['credito_dias', 'credito_cuotas', 'fecha_vencimiento']);
        });
    }
};
