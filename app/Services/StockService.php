<?php

namespace App\Services;

use App\Models\Producto;
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
        $producto->stock = round(((float) $producto->stock + $cantidad) * 100) / 100;
        $producto->save();

        return $producto->fresh();
    }

    public function disminuirStock(Producto $producto, float $cantidad): Producto
    {
        $cantidad = round($cantidad, 2);
        if ($cantidad < 0) {
            throw new InvalidArgumentException('La cantidad a disminuir no puede ser negativa.');
        }
        $nuevo = round(((float) $producto->stock - $cantidad) * 100) / 100;
        if ($nuevo < 0) {
            throw new InvalidArgumentException(
                "Stock insuficiente para {$producto->codigo}: disponible {$producto->stock}, solicitado {$cantidad}."
            );
        }
        $producto->stock = $nuevo;
        $producto->save();

        return $producto->fresh();
    }

    /**
     * Revierte un movimiento previo.
     * $tipoOperacion: 'compra' (había sumado → ahora resta) | 'venta' (había restado → ahora suma).
     */
    public function revertirStock(Producto $producto, float $cantidad, string $tipoOperacion): Producto
    {
        if ($tipoOperacion === 'compra') {
            $producto->stock = round(((float) $producto->stock - round($cantidad, 2)) * 100) / 100;
            $producto->save();

            return $producto->fresh();
        }

        return $this->aumentarStock($producto, $cantidad);
    }
}
