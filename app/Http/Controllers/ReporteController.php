<?php

namespace App\Http\Controllers;

use App\Models\CuotaVenta;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        // Filtro de facturación:
        // todos = todas las ventas
        // con = ventas con factura
        // sin = ventas sin factura
        $tipoFactura = $request->get('factura', 'todos');
        /*
        |--------------------------------------------------------------------------
        | REPORTE DE PROFORMAS
        |--------------------------------------------------------------------------
        */
        $proformas = Proforma::orderByDesc('id')->get();
        $emitidas = $proformas->count();
        $convertidas = $proformas
            ->where('estado', 'convertida')
            ->count();
        $tasa = $emitidas
            ? (int) round($convertidas / $emitidas * 100)
            : 0;
        $valorTotal = (float) $proformas->sum('total');
        $porEstado = [];

        foreach (Proforma::ESTADOS as $e) {

            $porEstado[$e] = $proformas
                ->where('estado', $e)
                ->count();
        }
        /*
        |--------------------------------------------------------------------------
        | FILTRO DE VENTAS
        |--------------------------------------------------------------------------
        */
        $ventasQuery = Venta::where('estado', 'activa');

        if ($tipoFactura === 'con') {
            $ventasQuery->where('tipo', 'con_factura');
        }

        if ($tipoFactura === 'sin') {
            $ventasQuery->where('tipo', 'sin_factura');
        }
        /*
        |--------------------------------------------------------------------------
        | VENTAS ÚLTIMOS 6 MESES
        |--------------------------------------------------------------------------
        */
        $meses = [];

        for ($i = 5; $i >= 0; $i--) {
            $meses[] = date('Y-m', strtotime("-{$i} months"));
        }
        $nombres = [
            'Ene',
            'Feb',
            'Mar',
            'Abr',
            'May',
            'Jun',
            'Jul',
            'Ago',
            'Sep',
            'Oct',
            'Nov',
            'Dic',
        ];
        $etiquetas = array_map(
            fn ($m) => $nombres[(int) substr($m, 5, 2) - 1],
            $meses
        );
        $ventasMes = [];
        foreach ($meses as $m) {
            $q = (clone $ventasQuery)
                ->where('fecha', 'like', "{$m}%");
            $ventasMes[] = (float) $q->sum('total');
        }
        /*
        |--------------------------------------------------------------------------
        | KPI VENTAS
        |--------------------------------------------------------------------------
        */
        $kpiVentas = [
            'total' => (float) (clone $ventasQuery)
                ->sum('total'),
            'contado' => (float) (clone $ventasQuery)
                ->where('modalidad', 'contado')
                ->sum('total'),
            'credito' => (float) (clone $ventasQuery)
                ->where('modalidad', 'credito')
                ->sum('total'),
            'documentos' => (clone $ventasQuery)
                ->count(),
        ];

        $hoy = now()->toDateString();
        $limiteSemana = now()->addDays(7)->toDateString();

        $cuotasPendientes = CuotaVenta::query()
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->whereColumn('pagado', '<', 'monto')
            ->whereHas('venta', fn ($query) => $query->where('estado', 'activa'));

        $ventasEntregaQuery = Venta::with(['sucursal:id,nombre', 'detalles:id,venta_id,cantidad,cantidad_entregada'])
            ->where('estado', 'activa')
            ->where('origen_siat', false)
            ->whereIn('entrega_estado', ['pendiente', 'parcial']);

        $productosSinFichaQuery = Producto::query()
            ->whereNull('ficha_tecnica_path');

        $alertasOperativas = [
            'total_cobrar' => (float) (clone $cuotasPendientes)
                ->sum(DB::raw('monto - pagado')),
            'total_vencido' => (float) (clone $cuotasPendientes)
                ->whereDate('fecha_vencimiento', '<', $hoy)
                ->sum(DB::raw('monto - pagado')),
            'por_vencer' => (clone $cuotasPendientes)
                ->whereBetween('fecha_vencimiento', [$hoy, $limiteSemana])
                ->count(),
            'entregas_pendientes' => (clone $ventasEntregaQuery)
                ->count(),
            'productos_sin_ficha' => (clone $productosSinFichaQuery)
                ->count(),
        ];

        $cuotasCriticas = (clone $cuotasPendientes)
            ->with(['venta:id,numero,cliente_nombre,fecha,total,pagado,modalidad,estado'])
            ->whereDate('fecha_vencimiento', '<=', $limiteSemana)
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->limit(8)
            ->get();

        $ventasEntrega = (clone $ventasEntregaQuery)
            ->orderByRaw("CASE entrega_estado WHEN 'parcial' THEN 0 ELSE 1 END")
            ->orderBy('fecha')
            ->orderBy('id')
            ->limit(8)
            ->get();

        $productosSinFicha = (clone $productosSinFichaQuery)
            ->orderBy('descripcion')
            ->limit(8)
            ->get(['id', 'codigo', 'descripcion', 'marca', 'stock', 'stock_reservado', 'stock_min', 'ficha_tecnica_path']);

        return view('reportes.index', [
            'emitidas' => $emitidas,
            'convertidas' => $convertidas,
            'tasa' => $tasa,
            'valorTotal' => $valorTotal,
            'porEstado' => $porEstado,
            'proformas' => $proformas->take(50),
            'etiquetas' => $etiquetas,
            'ventasMes' => $ventasMes,
            'kpiVentas' => $kpiVentas,
            'facturaFiltro' => $tipoFactura,
            'alertasOperativas' => $alertasOperativas,
            'cuotasCriticas' => $cuotasCriticas,
            'ventasEntrega' => $ventasEntrega,
            'productosSinFicha' => $productosSinFicha,
        ]);
    }
}
