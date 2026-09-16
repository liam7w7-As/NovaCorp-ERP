<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    public function index()
    {
        $productos = Producto::orderBy('descripcion')->get();
        $valorizado = $productos->map(fn ($p) => [
            'producto' => $p,
            'valor' => round((float) $p->stock * (float) $p->costo, 2),
        ]);
        $totalValor = round($valorizado->sum('valor'), 2);

        return view('kardex.index', compact('productos', 'valorizado', 'totalValor'));
    }

    public function show(Producto $producto, Request $request)
    {
        $movimientos = collect();

        foreach ($producto->detalleCompras()->with('compra')->get() as $d) {
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
                'costo' => (float) $d->precio_unitario,
            ]);
        }
        foreach ($producto->detalleVentas()->with('venta')->get() as $d) {
            if (! $d->venta || $d->venta->estado !== 'activa') {
                continue;
            }
            $movimientos->push([
                'fecha' => $d->venta->fecha->format('Y-m-d'),
                'documento' => $d->venta->numero,
                'tipo' => 'VENTA',
                'detalle' => $d->venta->cliente_nombre,
                'entrada' => 0,
                'salida' => (float) $d->cantidad,
                'costo' => (float) $d->precio_unitario,
            ]);
        }

        $movimientos = $movimientos->sortBy([['fecha', 'asc'], ['documento', 'asc']])->values();
        $saldo = 0;
        $movimientos = $movimientos->map(function ($m) use (&$saldo) {
            $saldo = round($saldo + $m['entrada'] - $m['salida'], 2);
            $m['saldo'] = $saldo;

            return $m;
        });

        $productos = Producto::orderBy('descripcion')->get(['id', 'codigo', 'descripcion']);

        return view('kardex.show', compact('producto', 'movimientos', 'productos'));
    }
}
