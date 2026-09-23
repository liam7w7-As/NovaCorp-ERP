<?php

namespace App\Services;

/**
 * Descuentos con tipo: monto fijo (Bs) o porcentaje sobre el subtotal.
 * El porcentaje se limita a 0–100.
 */
class Descuentos
{
    public const FIJO = 'fijo';

    public const PORCENTAJE = 'porcentaje';

    public static function normalizarTipo(?string $tipo): string
    {
        return $tipo === self::PORCENTAJE ? self::PORCENTAJE : self::FIJO;
    }

    /**
     * Monto del descuento en Bs a partir del valor ingresado.
     */
    public static function monto(float $subtotal, ?float $valor, ?string $tipo = self::FIJO): float
    {
        $valor = max(0, round((float) ($valor ?? 0), 2));
        $subtotal = max(0, round($subtotal, 2));

        if (self::normalizarTipo($tipo) === self::PORCENTAJE) {
            return round($subtotal * min($valor, 100) / 100, 2);
        }

        return $valor;
    }

    /**
     * Total neto: subtotal menos descuento, nunca negativo.
     */
    public static function total(float $subtotal, ?float $valor, ?string $tipo = self::FIJO): float
    {
        return max(0, round($subtotal - self::monto($subtotal, $valor, $tipo), 2));
    }

    /**
     * Etiqueta para mostrar: "10% (Bs 25,50)" o "Bs 25,50".
     */
    public static function etiqueta(float $subtotal, ?float $valor, ?string $tipo = self::FIJO): string
    {
        $tipo = self::normalizarTipo($tipo);
        $monto = self::monto($subtotal, $valor, $tipo);

        if ($tipo === self::PORCENTAJE) {
            $porcentaje = rtrim(rtrim(number_format(min(max((float) ($valor ?? 0), 0), 100), 2, '.', ''), '0'), '.');

            return "{$porcentaje}% (Bs ".number_format($monto, 2).')';
        }

        return 'Bs '.number_format($monto, 2);
    }
}
