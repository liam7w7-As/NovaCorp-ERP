<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Proveedor;
use App\Models\Venta;

class PapeleraController extends Controller
{
    public const MAPA = [
        'productos' => Producto::class,
        'clientes' => Cliente::class,
        'proveedores' => Proveedor::class,
        'compras' => Compra::class,
        'ventas' => Venta::class,
        'proformas' => Proforma::class,
        'comprobantes' => Comprobante::class,
    ];

    public const ETIQUETAS = [
        'productos' => 'Productos',
        'clientes' => 'Clientes',
        'proveedores' => 'Proveedores',
        'compras' => 'Compras',
        'ventas' => 'Ventas',
        'proformas' => 'Proformas',
        'comprobantes' => 'Comprobantes',
    ];

    protected function clase(string $modelo): string
    {
        abort_unless(isset(self::MAPA[$modelo]), 404);

        return self::MAPA[$modelo];
    }

    protected function titulo(object $registro): string
    {
        foreach (['numero', 'nombre', 'codigo', 'concepto', 'numero_factura'] as $campo) {
            if (! empty($registro->{$campo})) {
                return (string) $registro->{$campo};
            }
        }

        return '#'.$registro->getKey();
    }

    public function index()
    {
        $grupos = [];
        foreach (self::MAPA as $clave => $clase) {
            $items = $clase::onlyTrashed()->orderByDesc('deleted_at')->limit(50)->get();
            if ($items->isNotEmpty()) {
                $grupos[$clave] = $items;
            }
        }

        return view('papelera.index', [
            'grupos' => $grupos,
            'etiquetas' => self::ETIQUETAS,
        ]);
    }

    public function restaurar(string $modelo, int $id)
    {
        $clase = $this->clase($modelo);
        $registro = $clase::onlyTrashed()->findOrFail($id);
        $registro->restore();

        return back()->with('exito', $this->titulo($registro).' restaurado. Revisa el stock si era un documento.');
    }

    public function eliminar(string $modelo, int $id)
    {
        $clase = $this->clase($modelo);
        $registro = $clase::onlyTrashed()->findOrFail($id);
        $titulo = $this->titulo($registro);
        $registro->forceDelete();

        return back()->with('exito', $titulo.' eliminado definitivamente');
    }
}
