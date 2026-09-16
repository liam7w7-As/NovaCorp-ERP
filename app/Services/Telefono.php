<?php

namespace App\Services;

class Telefono
{
    public static function normalizar(string $telefono): string
    {
        $normalizado = preg_replace('/\D+/', '', $telefono) ?? '';

        if (str_starts_with($normalizado, '00')) {
            $normalizado = substr($normalizado, 2);
        }

        if (strlen($normalizado) === 8) {
            return '591'.$normalizado;
        }

        return $normalizado;
    }
}
