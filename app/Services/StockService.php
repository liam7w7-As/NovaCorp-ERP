<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    public function disponible(Producto $producto): float
    {
        return round(max(0, (float) $producto->stock - (float) $producto->stock_reservado), 2);
    }

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
        $cantidad = $this->normalizarCantidad($cantidad, 'aumentar');

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $bloqueado->stock = round(((float) $bloqueado->stock + $cantidad) * 100) / 100;
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    public function disminuirStock(Producto $producto, float $cantidad): Producto
    {
        $cantidad = $this->normalizarCantidad($cantidad, 'disminuir');

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $disponible = $this->disponible($bloqueado);
            if ($disponible < $cantidad) {
                throw new InvalidArgumentException(
                    "Stock insuficiente para {$bloqueado->codigo}: disponible {$disponible}, solicitado {$cantidad}."
                );
            }
            $nuevo = round(((float) $bloqueado->stock - $cantidad) * 100) / 100;
            if ($nuevo < 0) {
                throw new InvalidArgumentException(
                    "Stock insuficiente para {$bloqueado->codigo}: stock físico {$bloqueado->stock}, solicitado {$cantidad}."
                );
            }
            $bloqueado->stock = $nuevo;
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    public function reservarStock(Producto $producto, float $cantidad): Producto
    {
        $cantidad = $this->normalizarCantidad($cantidad, 'reservar');

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $disponible = $this->disponible($bloqueado);
            if ($disponible < $cantidad) {
                throw new InvalidArgumentException(
                    "Stock insuficiente para {$bloqueado->codigo}: disponible {$disponible}, solicitado {$cantidad}."
                );
            }
            $bloqueado->stock_reservado = round(((float) $bloqueado->stock_reservado + $cantidad) * 100) / 100;
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    public function liberarReserva(Producto $producto, float $cantidad): Producto
    {
        $cantidad = $this->normalizarCantidad($cantidad, 'liberar');

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            if ((float) $bloqueado->stock_reservado + 0.0001 < $cantidad) {
                throw new InvalidArgumentException(
                    "No se puede liberar reserva de {$bloqueado->codigo}: reservado {$bloqueado->stock_reservado}, solicitado {$cantidad}."
                );
            }
            $bloqueado->stock_reservado = round(max(0, (float) $bloqueado->stock_reservado - $cantidad), 2);
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    public function entregarStockReservado(Producto $producto, float $cantidad): Producto
    {
        $cantidad = $this->normalizarCantidad($cantidad, 'entregar');

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            if ((float) $bloqueado->stock_reservado + 0.0001 < $cantidad) {
                throw new InvalidArgumentException(
                    "No se puede entregar {$bloqueado->codigo}: reservado {$bloqueado->stock_reservado}, solicitado {$cantidad}."
                );
            }
            if ((float) $bloqueado->stock + 0.0001 < $cantidad) {
                throw new InvalidArgumentException(
                    "No se puede entregar {$bloqueado->codigo}: stock físico {$bloqueado->stock}, solicitado {$cantidad}."
                );
            }

            $bloqueado->stock = round(((float) $bloqueado->stock - $cantidad) * 100) / 100;
            $bloqueado->stock_reservado = round(max(0, (float) $bloqueado->stock_reservado - $cantidad), 2);
            $bloqueado->save();

            return $bloqueado->fresh();
        });
    }

    public function revertirEntrega(Producto $producto, float $cantidad): Producto
    {
        return $this->aumentarStock($producto, $cantidad);
    }

    public function revertirEntregaAReserva(Producto $producto, float $cantidad): Producto
    {
        $cantidad = $this->normalizarCantidad($cantidad, 'revertir entrega');

        return DB::transaction(function () use ($producto, $cantidad) {
            $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $bloqueado->stock = round(((float) $bloqueado->stock + $cantidad) * 100) / 100;
            $bloqueado->stock_reservado = round(((float) $bloqueado->stock_reservado + $cantidad) * 100) / 100;
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
            $cantidad = $this->normalizarCantidad($cantidad, 'revertir compra');

            return DB::transaction(function () use ($producto, $cantidad) {
                $bloqueado = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
                $nuevo = round(((float) $bloqueado->stock - $cantidad) * 100) / 100;
                if ($nuevo < (float) $bloqueado->stock_reservado) {
                    throw new InvalidArgumentException(
                        "No se puede revertir la compra: {$bloqueado->codigo} tiene {$bloqueado->stock_reservado} reservado y quedaría con stock físico {$nuevo}."
                    );
                }
                $bloqueado->stock = $nuevo;
                $bloqueado->save();

                return $bloqueado->fresh();
            });
        }

        return $this->aumentarStock($producto, $cantidad);
    }

    private function normalizarCantidad(float $cantidad, string $accion): float
    {
        $cantidad = round($cantidad, 2);
        if ($cantidad < 0) {
            throw new InvalidArgumentException("La cantidad a {$accion} no puede ser negativa.");
        }

        return $cantidad;
    }
}
