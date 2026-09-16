<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * Ajusta el stock de un producto.
     * $tipo: 'aumentar' | 'disminuir' (cualquier otro valor suma $cantidad tal cual, puede ser negativa).
     */
    public function ajustarStock(Producto $producto, float $cantidad, string $tipo = 'aumentar'): Producto
    {
        if ($tipo === 'disminuir') {
            return $this->disminuirStock($producto, $cantidad);
        }

        return $this->aumentarStock($producto, $cantidad);
    }

    public function aumentarStock(Producto $producto, float $cantidad): Producto
    {
        $cantidad = round($cantidad, 2);
        if ($cantidad < 0) {
            throw new InvalidArgumentException('La cantidad a aumentar no puede ser negativa.');
        }

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $bloqueado->stock = round(((float) $bloqueado->stock + $cantidad) * 100) / 100;
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    public function disminuirStock(Producto $producto, float $cantidad): Producto
    {
        $cantidad = round($cantidad, 2);
        if ($cantidad < 0) {
            throw new InvalidArgumentException('La cantidad a disminuir no puede ser negativa.');
        }

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $nuevo = round(((float) $bloqueado->stock - $cantidad) * 100) / 100;
            if ($nuevo < 0) {
                throw new InvalidArgumentException(
                    "Stock insuficiente para {$bloqueado->codigo}: disponible {$bloqueado->stock}, solicitado {$cantidad}."
                );
            }
            $bloqueado->stock = $nuevo;
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    /**
     * Revierte un movimiento previo.
     * $tipoOperacion: 'compra' (había sumado → ahora resta) | 'venta' (había restado → ahora suma).
     */
    public function revertirStock(Producto $producto, float $cantidad, string $tipoOperacion): Producto
    {
        if ($tipoOperacion === 'compra') {
            $cantidad = round($cantidad, 2);

            return DB::transaction(function () use ($producto, $cantidad) {
                $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
                $nuevo = round(((float) $bloqueado->stock - $cantidad) * 100) / 100;
                if ($nuevo < 0) {
                    throw new InvalidArgumentException(
                        "No se puede revertir la compra: {$bloqueado->codigo} quedaría con stock negativo (disp. {$bloqueado->stock}, a revertir {$cantidad})."
                    );
                }
                $bloqueado->stock = $nuevo;
                $bloqueado->save();

                return $bloqueado->fresh();
            });
        }

        return $this->aumentarStock($producto, $cantidad);
    }
}
