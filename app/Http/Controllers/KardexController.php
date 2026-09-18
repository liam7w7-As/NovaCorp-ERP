<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        $productos = Producto::query()
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            }))
            ->orderBy('descripcion')
            ->paginate(50)
            ->withQueryString();

        $valorizado = $productos->getCollection()->map(fn ($p) => [
            'producto' => $p,
            'valor' => round((float) $p->stock * (float) $p->costo, 2),
        ]);
        $totalValor = round((float) Producto::query()
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            }))
            ->selectRaw('COALESCE(SUM(stock * costo), 0) as total')->value('total'), 2);

        return view('kardex.index', compact('productos', 'valorizado', 'totalValor') + ['q' => $q]);
    }

    public function show(Producto $producto, Request $request)
    {
        $data = $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
        ]);
        $desde = $data['desde'] ?? null;
        $hasta = $data['hasta'] ?? null;

        $enRango = fn ($query, string $relacion) => $query->whereHas($relacion, function ($q) use ($relacion, $desde, $hasta) {
            if ($relacion === 'notaEntrega') {
                $q->where('estado', 'emitida');
            }
            if ($desde) {
                $q->whereDate('fecha', '>=', $desde);
            }
            if ($hasta) {
                $q->whereDate('fecha', '<=', $hasta);
            }
        });

        // Saldo de arrastre: movimientos anteriores al filtro (mismas reglas de inclusión).
        $saldoInicial = 0;
        if ($desde) {
            $entradas = (float) $producto->detalleCompras()
                ->whereHas('compra', fn ($q) => $q->whereDate('fecha', '<', $desde))
                ->sum('cantidad');
            $salidas = (float) $producto->detalleNotaEntregas()
                ->whereHas('notaEntrega', fn ($q) => $q->where('estado', 'emitida')->whereDate('fecha', '<', $desde))
                ->sum('cantidad');
            $saldoInicial = round($entradas - $salidas, 2);
        }

        $movimientos = collect();

        foreach ($enRango($producto->detalleCompras()->with('compra'), 'compra')->get() as $d) {
            if (! $d->compra) {
                continue;
            }
            $movimientos->push([
                'fecha' => $d->compra->fecha->format('Y-m-d'),
                'documento' => $d->compra->numero,
                'tipo' => 'COMPRA',
                'detalle' => $d->compra->proveedor_nombre,
                'entrada' => (float) $d->cantidad,
                'salida' => 0,
                'costo' => (float) $producto->costo,
            ]);
        }
        foreach ($enRango($producto->detalleNotaEntregas()->with('notaEntrega.venta'), 'notaEntrega')->get() as $d) {
            if (! $d->notaEntrega || $d->notaEntrega->estado !== 'emitida') {
                continue;
            }
            $movimientos->push([
                'fecha' => $d->notaEntrega->fecha->format('Y-m-d'),
                'documento' => $d->notaEntrega->numero,
                'tipo' => 'ENTREGA',
                'detalle' => $d->notaEntrega->cliente_nombre.' · Venta '.($d->notaEntrega->venta?->numero ?? '—'),
                'entrada' => 0,
                'salida' => (float) $d->cantidad,
                'costo' => (float) $d->precio_unitario,
            ]);
        }

        $movimientos = $movimientos->sortBy([['fecha', 'asc'], ['documento', 'asc']])->values();
        $saldo = $saldoInicial;
        $movimientos = $movimientos->map(function ($m) use (&$saldo) {
            $saldo = round($saldo + $m['entrada'] - $m['salida'], 2);
            $m['saldo'] = $saldo;

            return $m;
        });

        $productos = Producto::orderBy('descripcion')->get(['id', 'codigo', 'descripcion']);

        return view('kardex.show', compact('producto', 'movimientos', 'productos', 'desde', 'hasta', 'saldoInicial'));
    }
}
