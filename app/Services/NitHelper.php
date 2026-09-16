<?php

namespace App\Services;

/**
 * Verificación local de NIT/CI boliviano.
 * - formatoValido(): bloqueante (numérico, 5-13 dígitos).
 * - digitoEsperado()/coincideDigito(): informativo (módulo 11). La verificación
 *   oficial de estado se hace contra el SIN en el contraste piloto (Fase 6).
 */
class NitHelper
{
    public static function soloDigitos(?string $nit): string
    {
        return preg_replace('/\D/', '', (string) $nit);
    }

    public static function formatoValido(?string $nit): bool
    {
        if ($nit === null || $nit === '') {
            return true; // el NIT es opcional en clientes
        }
        $d = self::soloDigitos($nit);

        return $d !== '' && strlen($d) >= 5 && strlen($d) <= 13;
    }

    /**
     * Dígito verificador módulo 11 (pesos 2-7 cíclicos de derecha a izquierda).
     * Calculado sobre el cuerpo sin el último dígito.
     */
    public static function digitoEsperado(string $nit): ?int
    {
        $d = self::soloDigitos($nit);
        if (strlen($d) < 2) {
            return null;
        }
        $cuerpo = substr($d, 0, -1);
        $suma = 0;
        $peso = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += ((int) $cuerpo[$i]) * $peso;
            $peso = $peso === 7 ? 2 : $peso + 1;
        }
        $resto = $suma % 11;
        if ($resto === 0) {
            return 0;
        }

        return 11 - $resto; // 1-10 (10 = "X" teórico, se reporta como 10)
    }

    public static function coincideDigito(?string $nit): ?bool
    {
        $d = self::soloDigitos($nit);
        if (strlen($d) < 2) {
            return null;
        }
        $esperado = self::digitoEsperado($d);
        if ($esperado === null || $esperado === 10) {
            return null; // no concluyente
        }

        return ((int) substr($d, -1)) === $esperado;
    }

    public static function mensajeFormato(): string
    {
        return 'NIT/CI inválido: solo dígitos, entre 5 y 13 caracteres.';
    }
}
