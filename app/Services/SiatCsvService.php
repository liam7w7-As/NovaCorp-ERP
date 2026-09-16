<?php

namespace App\Services;

/**
 * Puerto a PHP de DB.parsearCSV y helpers SIAT de js/data.js
 */
class SiatCsvService
{
    /**
     * Parser CSV simple: soporta comas y comillas dobles.
     * Devuelve array de objetos [encabezado => valor].
     */
    public static function parsearCSV(string $texto): array
    {
        // Quitar BOM
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);

        $filas = [];
        $fila = [];
        $campo = '';
        $dentroComillas = false;
        $len = strlen($texto);

        for ($i = 0; $i < $len; $i++) {
            $c = $texto[$i];
            if ($dentroComillas) {
                if ($c === '"' && ($i + 1) < $len && $texto[$i + 1] === '"') {
                    $campo .= '"';
                    $i++;
                } elseif ($c === '"') {
                    $dentroComillas = false;
                } else {
                    $campo .= $c;
                }
            } else {
                if ($c === '"') {
                    $dentroComillas = true;
                } elseif ($c === ',') {
                    $fila[] = $campo;
                    $campo = '';
                } elseif ($c === "\n" || $c === "\r") {
                    if ($c === "\r" && ($i + 1) < $len && $texto[$i + 1] === "\n") {
                        $i++;
                    }
                    $fila[] = $campo;
                    $campo = '';
                    if (count($fila) > 1 || trim($fila[0] ?? '') !== '') {
                        $filas[] = $fila;
                    }
                    $fila = [];
                } else {
                    $campo .= $c;
                }
            }
        }
        if ($campo !== '' || count($fila)) {
            $fila[] = $campo;
            $filas[] = $fila;
        }

        if (empty($filas)) {
            return [];
        }

        $encabezados = array_map(fn ($h) => trim($h), array_shift($filas));

        return array_map(function ($f) use ($encabezados) {
            $obj = [];
            foreach ($encabezados as $i => $h) {
                $obj[$h] = trim($f[$i] ?? '');
            }

            return $obj;
        }, $filas);
    }

    public static function normalizarEncabezado(?string $s): string
    {
        $s = trim(mb_strtoupper($s ?? ''));
        // Quitar tildes sin depender de intl
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U', 'Ñ' => 'N',
        ]);

        return $s;
    }

    public static function obtenerCampo(array $fila, string ...$posiblesNombres): string
    {
        foreach ($posiblesNombres as $nombre) {
            $objetivo = self::normalizarEncabezado($nombre);
            foreach ($fila as $k => $v) {
                if (self::normalizarEncabezado($k) === $objetivo) {
                    return trim((string) $v);
                }
            }
        }

        return '';
    }

    public static function fechaSiatAiso(string $fechaDDMMYYYY): string
    {
        $partes = explode('/', trim($fechaDDMMYYYY));
        if (count($partes) !== 3) {
            return date('Y-m-d');
        }
        [$d, $m, $y] = $partes;

        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }

    /**
     * ¿Parece un Libro de Compras/Ventas del SIAT? (trae código de autorización)
     */
    public static function pareceSiat(array $filas): bool
    {
        if (empty($filas)) {
            return false;
        }
        $keys = array_keys($filas[0]);
        foreach ($keys as $k) {
            if (self::normalizarEncabezado($k) === 'CODIGO DE AUTORIZACION') {
                return true;
            }
        }

        return false;
    }
}
