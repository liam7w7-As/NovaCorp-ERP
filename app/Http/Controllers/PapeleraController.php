<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Proveedor;
use App\Models\Venta;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    public function restaurar(string $modelo, int $id, StockService $stock)
    {
        $clase = $this->clase($modelo);
        $registro = $clase::onlyTrashed()->findOrFail($id);

        if ($registro instanceof Venta) {
            return $this->restaurarVenta($registro, $stock);
        }
        if ($registro instanceof Compra) {
            return $this->restaurarCompra($registro, $stock);
        }

        $registro->restore();

        return back()->with('exito', $this->titulo($registro).' restaurado.');
    }

    /**
     * Restaurar una venta revierte lo que hizo el eliminado:
     * vuelve a reservar lo pendiente, descuenta lo entregado y restaura su comprobante.
     */
    protected function restaurarVenta(Venta $venta, StockService $stock)
    {
        try {
            DB::transaction(function () use ($venta, $stock) {
                $venta->load('detalles.producto');
                if ($venta->estado === 'activa' && ! $venta->origen_siat) {
                    $ids = $venta->detalles->map(fn ($det) => $det->producto_id)->filter()->all();
                    $bloqueados = Producto::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
                    foreach ($venta->detalles as $det) {
                        if (! $det->producto_id) {
                            continue;
                        }
                        $producto = $bloqueados[$det->producto_id];
                        $disponible = round((float) $producto->stock - (float) $producto->stock_reservado, 2);
                        if ($disponible < (float) $det->cantidad) {
                            throw new InvalidArgumentException(
                                "No se puede restaurar {$venta->numero}: stock insuficiente para {$det->codigo_producto} (disp. {$disponible}, req. {$det->cantidad})."
                            );
                        }
                    }
                    foreach ($venta->detalles as $det) {
                        if ($det->producto_id) {
                            $entregado = round((float) $det->cantidad_entregada, 2);
                            $pendiente = round(max(0, (float) $det->cantidad - $entregado), 2);
                            if ($entregado > 0) {
                                $stock->disminuirStock($det->producto, $entregado);
                            }
                            if ($pendiente > 0) {
                                $stock->reservarStock($det->producto->fresh(), $pendiente);
                            }
                        }
                    }
                }
                $venta->restore();
                Comprobante::onlyTrashed()->where('origen_venta_id', $venta->id)->restore();
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', $venta->numero.' restaurada con stock y comprobante.');
    }

    /**
     * Restaurar una compra revierte lo que hizo el eliminado:
     * vuelve a sumar stock y restaura su comprobante.
     */
    protected function restaurarCompra(Compra $compra, StockService $stock)
    {
        DB::transaction(function () use ($compra, $stock) {
            $compra->load('detalles.producto');
            if (! $compra->origen_siat) {
                foreach ($compra->detalles as $det) {
                    if ($det->producto_id) {
                        $stock->aumentarStock($det->producto, (float) $det->cantidad);
                    }
                }
            }
            $compra->restore();
            Comprobante::onlyTrashed()->where('origen_compra_id', $compra->id)->restore();
        });

        return back()->with('exito', $compra->numero.' restaurada con stock y comprobante.');
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
