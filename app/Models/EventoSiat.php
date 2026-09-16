<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoSiat extends Model
{
    protected $table = 'eventos_siat';

    protected $fillable = [
        'metodo', 'parametros', 'respuesta', 'exitoso', 'fecha',
    ];

    protected $casts = [
        'exitoso' => 'boolean',
        'fecha' => 'datetime',
    ];

    public static function registrar(string $metodo, $parametros, $respuesta, bool $exitoso): self
    {
        return static::create([
            'metodo' => $metodo,
            'parametros' => is_string($parametros) ? $parametros : json_encode($parametros, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'respuesta' => is_string($respuesta) ? mb_substr($respuesta, 0, 16000) : json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'exitoso' => $exitoso,
            'fecha' => now(),
        ]);
    }
}
