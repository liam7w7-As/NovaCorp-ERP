<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Proveedor;
use App\Models\Venta;
use App\Services\Permisos;
use Illuminate\Http\Request;

class BusquedaGlobalController extends Controller
{
    public function buscar(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $u = $request->user();
        $grupos = [];

        if (Permisos::puede($u, 'productos')) {
            $grupos[] = [
                'titulo' => 'Productos',
                'items' => Producto::where(fn ($s) => $s
                    ->where('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%"))
                    ->limit(5)->get(['codigo', 'descripcion'])
                    ->map(fn ($p) => [
                        'texto' => $p->codigo.' — '.$p->descripcion,
                        'url' => route('productos.index', ['q' => $p->codigo]),
                    ])->values(),
            ];
        }
        if (Permisos::puede($u, 'clientes')) {
            $grupos[] = [
                'titulo' => 'Clientes',
                'items' => Cliente::where('nombre', 'like', "%{$q}%")->limit(5)
                    ->get()->map(fn ($c) => [
                        'texto' => $c->nombre,
                        'url' => route('clientes.index', ['q' => $c->nombre]),
                    ])->values(),
            ];
        }
        if (Permisos::puede($u, 'proveedores')) {
            $grupos[] = [
                'titulo' => 'Proveedores',
                'items' => Proveedor::where('nombre', 'like', "%{$q}%")->limit(5)
                    ->get()->map(fn ($p) => [
                        'texto' => $p->nombre,
                        'url' => route('proveedores.index', ['q' => $p->nombre]),
                    ])->values(),
            ];
        }
        if (Permisos::puede($u, 'ventas')) {
            $grupos[] = [
                'titulo' => 'Ventas',
                'items' => Venta::where(fn ($s) => $s
                    ->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%"))
                    ->limit(5)->get()
                    ->map(fn ($v) => [
                        'texto' => $v->numero.' — '.$v->cliente_nombre,
                        'url' => route('ventas.index', ['q' => $v->numero]),
                    ])->values(),
            ];
        }
        if (Permisos::puede($u, 'compras')) {
            $grupos[] = [
                'titulo' => 'Compras',
                'items' => Compra::where(fn ($s) => $s
                    ->where('numero', 'like', "%{$q}%")
                    ->orWhere('proveedor_nombre', 'like', "%{$q}%"))
                    ->limit(5)->get()
                    ->map(fn ($c) => [
                        'texto' => $c->numero.' — '.$c->proveedor_nombre,
                        'url' => route('compras.index', ['q' => $c->numero]),
                    ])->values(),
            ];
        }
        if (Permisos::puede($u, 'proformas')) {
            $grupos[] = [
                'titulo' => 'Proformas',
                'items' => Proforma::where(fn ($s) => $s
                    ->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%"))
                    ->limit(5)->get()
                    ->map(fn ($p) => [
                        'texto' => $p->numero.' — '.$p->cliente_nombre,
                        'url' => route('proformas.show', $p),
                    ])->values(),
            ];
        }
        if (Permisos::puede($u, 'comprobantes')) {
            $grupos[] = [
                'titulo' => 'Comprobantes',
                'items' => Comprobante::where(fn ($s) => $s
                    ->where('numero', 'like', "%{$q}%")
                    ->orWhere('concepto', 'like', "%{$q}%"))
                    ->limit(5)->get()
                    ->map(fn ($c) => [
                        'texto' => $c->numero.' — '.$c->concepto,
                        'url' => route('comprobantes.index', ['q' => $c->numero]),
                    ])->values(),
            ];
        }

        return response()->json(array_values(array_filter($grupos, fn ($g) => $g['items']->isNotEmpty())));
    }
}
