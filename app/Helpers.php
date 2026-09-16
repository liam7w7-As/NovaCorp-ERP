<?php

if (! function_exists('formatoMoneda')) {
    /**
     * Replica js/data.js formatoMoneda: punto decimal, coma de miles. Ej: 1,234.56
     */
    function formatoMoneda($n): string
    {
        $numero = (float) ($n ?? 0);

        return number_format($numero, 2, '.', ',');
    }
}

if (! function_exists('montoALetras')) {
    function _bloqueHasta999(int $num): string
    {
        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        if ($num === 0) {
            return '';
        }
        if ($num === 100) {
            return 'cien';
        }
        $texto = '';
        $c = intdiv($num, 100);
        $resto = $num % 100;
        if ($c > 0) {
            $texto .= $centenas[$c].' ';
        }
        if ($resto > 0) {
            if ($resto < 10) {
                $texto .= $unidades[$resto];
            } elseif ($resto < 20) {
                $texto .= $especiales[$resto - 10];
            } else {
                $d = intdiv($resto, 10);
                $u = $resto % 10;
                if ($d === 2 && $u > 0) {
                    $texto .= 'veinti'.$unidades[$u];
                } else {
                    $texto .= $decenas[$d];
                    if ($u > 0) {
                        $texto .= ' y '.$unidades[$u];
                    }
                }
            }
        }

        return trim($texto);
    }

    /**
     * Replica js/data.js montoALetras: "Mil setecientos dieciocho 56/100 bolivianos"
     */
    function montoALetras($monto): string
    {
        $abs = abs((float) $monto);
        $entero = (int) floor($abs);
        $centavos = (int) round(($abs - $entero) * 100);
        if ($entero === 0) {
            $texto = 'cero';
        } else {
            $resultado = '';
            $resto = $entero;
            $millones = intdiv($resto, 1000000);
            $resto %= 1000000;
            if ($millones > 0) {
                $resultado .= ($millones === 1 ? 'un millón' : _bloqueHasta999($millones).' millones').' ';
            }
            $miles = intdiv($resto, 1000);
            $resto %= 1000;
            if ($miles > 0) {
                $resultado .= ($miles === 1 ? 'mil' : _bloqueHasta999($miles).' mil').' ';
            }
            if ($resto > 0) {
                $resultado .= _bloqueHasta999($resto);
            }
            $texto = trim($resultado);
        }

        return ucfirst($texto).' '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 bolivianos';
    }
}
