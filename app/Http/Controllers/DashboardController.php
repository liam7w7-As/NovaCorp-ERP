<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $mesParam = $request->get('mes', '');
        $mesActual = preg_match('/^\d{4}-\d{2}$/', $mesParam) ? $mesParam : now()->format('Y-m');
        $mesAnterior = date('Y-m', strtotime($mesActual.'-01 -1 month'));

        $hoy = now();

        $ventasActivas = fn () => Venta::where('estado', 'activa');

        // ---- KPIs del mes con variación vs mes anterior ----
        $totalVentasMes = (float) (clone $ventasActivas())->where('fecha', 'like', "{$mesActual}%")->sum('total');
        $totalVentasMesAnt = (float) (clone $ventasActivas())->where('fecha', 'like', "{$mesAnterior}%")->sum('total');
        $totalComprasMes = (float) Compra::where('fecha', 'like', "{$mesActual}%")->sum('total');
        $totalComprasMesAnt = (float) Compra::where('fecha', 'like', "{$mesAnterior}%")->sum('total');

        $utilidadMes = $this->utilidadRango("{$mesActual}-01", $hoy->copy()->endOfMonth()->format('Y-m-d'));
        $utilidadMesAnt = $this->utilidadRango(
            date('Y-m-01', strtotime($mesAnterior.'-01')),
            date('Y-m-t', strtotime($mesAnterior.'-01'))
        );

        $valorInventario = (float) Producto::sum(DB::raw('stock * costo'));
        $totalProductos = Producto::count();

        // Flujo de caja del mes (comprobantes)
        $ingresosMes = (float) Comprobante::where('tipo', 'ingreso')->where('fecha', 'like', "{$mesActual}%")->sum('monto');
        $egresosMes = (float) Comprobante::where('tipo', 'egreso')->where('fecha', 'like', "{$mesActual}%")->sum('monto');
        $flujoMes = round($ingresosMes - $egresosMes, 2);

        // ---- Últimos 6 meses ----
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $meses[] = $hoy->copy()->subMonths($i)->format('Y-m');
        }
        $nombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $etiquetas = array_map(fn ($m) => $nombres[(int) substr($m, 5, 2) - 1], $meses);

        $datosVentas = [];
        $datosCompras = [];
        $datosIngresos = [];
        $datosEgresos = [];
        $datosUtilidad = [];
        foreach ($meses as $m) {
            $datosVentas[] = (float) (clone $ventasActivas())->where('fecha', 'like', "{$m}%")->sum('total');
            $datosCompras[] = (float) Compra::where('fecha', 'like', "{$m}%")->sum('total');
            $datosIngresos[] = (float) Comprobante::where('tipo', 'ingreso')->where('fecha', 'like', "{$m}%")->sum('monto');
            $datosEgresos[] = (float) Comprobante::where('tipo', 'egreso')->where('fecha', 'like', "{$m}%")->sum('monto');
            $datosUtilidad[] = $this->utilidadRango(
                date('Y-m-01', strtotime($m.'-01')),
                date('Y-m-t', strtotime($m.'-01'))
            );
        }

        // ---- Ventas por marca (doughnut) ----
        $ventasPorMarca = DB::table('detalle_venta')
            ->join('ventas', 'ventas.id', '=', 'detalle_venta.venta_id')
            ->leftJoin('productos', 'productos.id', '=', 'detalle_venta.producto_id')
            ->where('ventas.estado', 'activa')
            ->selectRaw("COALESCE(NULLIF(productos.marca, ''), 'Sin marca') as marca, SUM(detalle_venta.cantidad * detalle_venta.precio_unitario) as total")
            ->groupBy('marca')
            ->orderByDesc('total')
            ->get();

        // ---- Rankings ----
        $topClientes = (clone $ventasActivas())
            ->selectRaw('cliente_nombre as nombre, SUM(total) as total')
            ->groupBy('cliente_nombre')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $topProductos = DB::table('detalle_venta')
            ->join('ventas', 'ventas.id', '=', 'detalle_venta.venta_id')
            ->where('ventas.estado', 'activa')
            ->selectRaw('codigo_producto as codigo, SUM(cantidad) as cantidad')
            ->groupBy('codigo_producto')
            ->orderByDesc('cantidad')
            ->limit(6)
            ->get();

        $stockCritico = Producto::whereColumn('stock', '<=', 'stock_min')
            ->orderBy('descripcion')
            ->limit(6)
            ->get();

        // ---- Estado de resultados del mes ----
        $margen = $totalVentasMes > 0 ? round($utilidadMes / $totalVentasMes * 100, 1) : 0;

        // ---- Resumen fiscal SIAT ----
        $creditoFiscal = (float) Compra::where('origen_siat', true)->sum('credito_fiscal');
        $debitoFiscal = (float) Venta::where('origen_siat', true)->where('estado', 'activa')->sum('debito_fiscal');
        $saldoFiscal = round($debitoFiscal - $creditoFiscal, 2);

        return view('dashboard', [
            'mes' => $mesActual,
            'totalVentasMes' => $totalVentasMes,
            'varVentas' => $this->variacion($totalVentasMes, $totalVentasMesAnt),
            'totalComprasMes' => $totalComprasMes,
            'varCompras' => $this->variacion($totalComprasMes, $totalComprasMesAnt),
            'utilidadMes' => $utilidadMes,
            'varUtilidad' => $this->variacion($utilidadMes, $utilidadMesAnt),
            'valorInventario' => $valorInventario,
            'totalProductos' => $totalProductos,
            'flujoMes' => $flujoMes,
            'ingresosMes' => $ingresosMes,
            'egresosMes' => $egresosMes,
            'etiquetas' => $etiquetas,
            'datosVentas' => $datosVentas,
            'datosCompras' => $datosCompras,
            'datosIngresos' => $datosIngresos,
            'datosEgresos' => $datosEgresos,
            'datosUtilidad' => $datosUtilidad,
            'ventasPorMarca' => $ventasPorMarca,
            'topClientes' => $topClientes,
            'topProductos' => $topProductos,
            'stockCritico' => $stockCritico,
            'margen' => $margen,
            'creditoFiscal' => $creditoFiscal,
            'debitoFiscal' => $debitoFiscal,
            'saldoFiscal' => $saldoFiscal,
        ]);
    }

    /**
     * Exporta ventas y compras del mes a CSV.
     */
    public function exportar(Request $request)
    {
        $mes = $request->get('mes', '');
        $mes = preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : now()->format('Y-m');

        $lineas = [];
        $lineas[] = 'Tipo,Numero,Fecha,Entidad,Total';
        $esc = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';

        foreach (Venta::where('fecha', 'like', "{$mes}%")->orderBy('fecha')->get() as $v) {
            $lineas[] = implode(',', ['VENTA', $v->numero, $v->fecha->format('Y-m-d'), $esc($v->cliente_nombre), $v->total, $v->estado]);
        }
        foreach (Compra::where('fecha', 'like', "{$mes}%")->orderBy('fecha')->get() as $c) {
            $lineas[] = implode(',', ['COMPRA', $c->numero, $c->fecha->format('Y-m-d'), $esc($c->proveedor_nombre), $c->total]);
        }

        return response(implode("\n", $lineas), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="dashboard-'.$mes.'.csv"',
        ]);
    }

    /**
     * Utilidad bruta = Σ (precio - costo_actual) * cantidad en ventas activas del rango.
     */
    protected function utilidadRango(string $desde, string $hasta): float
    {
        $detalles = DB::table('detalle_venta')
            ->join('ventas', 'ventas.id', '=', 'detalle_venta.venta_id')
            ->leftJoin('productos', 'productos.id', '=', 'detalle_venta.producto_id')
            ->where('ventas.estado', 'activa')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->select('detalle_venta.cantidad', 'detalle_venta.precio_unitario', 'productos.costo')
            ->get();

        $u = 0;
        foreach ($detalles as $d) {
            $u += ((float) $d->precio_unitario - (float) ($d->costo ?? 0)) * (float) $d->cantidad;
        }

        return round($u, 2);
    }

    protected function variacion(float $actual, float $anterior): array
    {
        if ($anterior == 0) {
            return $actual > 0
                ? ['texto' => 'Nuevo', 'clase' => 'subio', 'icono' => 'arrow-up']
                : ['texto' => 'Sin cambios', 'clase' => 'neutro', 'icono' => 'dash'];
        }
        $pct = (int) round(($actual - $anterior) / $anterior * 100);
        if ($pct > 0) {
            return ['texto' => "+{$pct}% vs mes anterior", 'clase' => 'subio', 'icono' => 'arrow-up'];
        }
        if ($pct < 0) {
            return ['texto' => "{$pct}% vs mes anterior", 'clase' => 'bajo', 'icono' => 'arrow-down'];
        }

        return ['texto' => 'Igual que el mes anterior', 'clase' => 'neutro', 'icono' => 'dash'];
    }
}
