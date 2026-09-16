<?php

namespace App\Http\Controllers;

use App\Models\Proforma;
use App\Models\Venta;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request)
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
        ]);
    }
}
