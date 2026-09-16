<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProformaService
{
    public function __construct(
        protected ContadorService $contadores,
        protected StockService $stock,
        protected ComprobanteService $comprobantes,
    ) {}

    public function siguienteNumero(): string
    {
        return $this->contadores->siguienteUnico(
            'PR-',
            fn ($n) => Proforma::withTrashed()->where('numero', $n)->exists()
        );
    }

    /**
     * Calcula subtotal/descuento/total a partir de items [cantidad, precio].
     */
    public function calcularTotales(array $items, float $descuento = 0): array
    {
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal = round($subtotal + round((float) $it['cantidad'], 2) * round((float) $it['precio'], 2), 2);
        }
        $descuento = round($descuento, 2);
        $total = max(0, round($subtotal - $descuento, 2));

        return compact('subtotal', 'descuento', 'total');
    }

    public function cambiarEstado(Proforma $proforma, string $estado): Proforma
    {
        $estado = mb_strtolower($estado);

        if (! in_array($estado, Proforma::ESTADOS, true)) {
            throw new InvalidArgumentException("Estado {$estado} no válido.");
        }
        if ($proforma->estado === 'convertida') {
            throw new InvalidArgumentException('No se puede cambiar el estado de una proforma convertida.');
        }
        if ($estado === 'convertida') {
            throw new InvalidArgumentException('Usa la opción Convertir a Venta para marcarla como convertida.');
        }

        $proforma->update(['estado' => $estado]);

        return $proforma->fresh();
    }

    /**
     * Convierte una proforma (solo enviada/aprobada) en venta + comprobante de ingreso.
     * Descuenta stock real dentro de una transacción.
     */
    public function convertirAVenta(Proforma $proforma, string $tipo = 'sin_factura', string $modalidad = 'contado', string $metodo = 'Efectivo'): Venta
    {
        if ($proforma->estado === 'convertida') {
            throw new InvalidArgumentException('La proforma ya fue convertida.');
        }
        if (! $proforma->es_convertible) {
            throw new InvalidArgumentException(
                "Solo se pueden convertir proformas en estado enviada o aprobada (actual: {$proforma->estado})."
            );
        }
        if (! in_array($tipo, ['con_factura', 'sin_factura'], true)) {
            throw new InvalidArgumentException('Tipo de venta no válido.');
        }
        if (! in_array($modalidad, ['contado', 'credito'], true)) {
            throw new InvalidArgumentException('Modalidad no válida.');
        }

        return DB::transaction(function () use ($proforma, $tipo, $modalidad, $metodo) {
            $proforma->load('detalles.producto');

            if ($proforma->detalles->isEmpty()) {
                throw new InvalidArgumentException('La proforma no tiene items para convertir.');
            }

            // Bloquear filas de producto y validar stock dentro de la
            // transacción (evita TOCTOU entre la comprobación y el descuento).
            $ids = $proforma->detalles->map(fn ($det) => $det->producto_id)->filter()->all();
            $bloqueados = Producto::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            foreach ($proforma->detalles as $det) {
                if ($det->producto_id) {
                    $disp = (float) ($bloqueados[$det->producto_id]->stock ?? 0);
                    if ($disp < (float) $det->cantidad) {
                        throw new InvalidArgumentException(
                            "Stock insuficiente para {$det->codigo_producto} (disp. {$disp}, req. {$det->cantidad})."
                        );
                    }
                }
            }

            $venta = Venta::create([
                'numero' => $this->contadores->siguienteUnico(
                    $tipo === 'con_factura' ? 'FV-' : 'NV-',
                    fn ($n) => Venta::withTrashed()->where('numero', $n)->exists()
                ),
                'tipo' => $tipo,
                'modalidad' => $modalidad,
                'cliente_id' => $proforma->cliente_id,
                'cliente_nombre' => $proforma->cliente_nombre,
                'fecha' => date('Y-m-d'),
                'subtotal' => $proforma->subtotal,
                'descuento' => $proforma->descuento,
                'total' => $proforma->total,
                'base_df' => $tipo === 'con_factura' ? $proforma->total : null,
                'debito_fiscal' => $tipo === 'con_factura' ? round((float) $proforma->total * 0.13, 2) : null,
                'observaciones' => 'Generada desde proforma '.$proforma->numero,
            ]);

            foreach ($proforma->detalles as $det) {
                $venta->detalles()->create([
                    'producto_id' => $det->producto_id,
                    'codigo_producto' => $det->codigo_producto,
                    'descripcion_producto' => $det->descripcion_producto,
                    'cantidad' => $det->cantidad,
                    'precio_unitario' => $det->precio_unitario,
                    'subtotal' => $det->subtotal,
                ]);

                if ($det->producto) {
                    $this->stock->disminuirStock($det->producto->fresh(), (float) $det->cantidad);
                } elseif ($det->producto_id) {
                    $faltante = Producto::find($det->producto_id);
                    if ($faltante) {
                        $this->stock->disminuirStock($faltante, (float) $det->cantidad);
                    }
                }
            }

            $this->comprobantes->crearParaVenta($venta->fresh(), $metodo);

            $proforma->update(['estado' => 'convertida', 'venta_id' => $venta->id]);

            return $venta->fresh();
        });
    }
}
