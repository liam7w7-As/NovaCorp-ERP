<?php

namespace App\Services;

use App\Models\Contador;
use Illuminate\Support\Facades\DB;

class ContadorService
{
    /**
     * Devuelve el siguiente número de documento de forma atómica.
     * Ej: siguiente('FC-') → 'FC-000001'
     */
    public function siguiente(string $prefijo, int $longitud = 6): string
    {
        return DB::transaction(function () use ($prefijo, $longitud) {
            $contador = Contador::where('clave', $prefijo)->lockForUpdate()->first();

            if (! $contador) {
                $contador = Contador::create(['clave' => $prefijo, 'valor' => 0]);
            }

            $contador->increment('valor');
            $contador->refresh();

            return $prefijo.str_pad((string) $contador->valor, $longitud, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Reserva el siguiente número solo si el candidato ya está ocupado o es nulo.
     * Útil en importaciones que traen su propio número de documento.
     */
    public function reservarSiExiste(string $prefijo, ?string $numero): string
    {
        if ($numero) {
            return $numero;
        }

        return $this->siguiente($prefijo);
    }

    /**
     * Como siguiente(), pero salta números que ya existan según $existe.
     * Evita choques con registros en papelera (soft deletes) tras reinicios
     * de contadores. $existe recibe el candidato y retorna bool.
     */
    public function siguienteUnico(string $prefijo, callable $existe, int $longitud = 6, int $intentos = 50): string
    {
        for ($i = 0; $i < $intentos; $i++) {
            $numero = $this->siguiente($prefijo, $longitud);
            if (! $existe($numero)) {
                return $numero;
            }
        }

        throw new \RuntimeException("No se pudo generar un número único con prefijo {$prefijo}.");
    }
}
