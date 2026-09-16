<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoSin extends Model
{
    protected $table = 'catalogos_sin';

    protected $fillable = ['tipo', 'codigo', 'descripcion', 'extra'];

    public const TIPOS = [
        'actividad' => 'Actividades económicas',
        'producto' => 'Productos / servicios SIN',
        'documento' => 'Tipos de documento de identidad',
        'pago' => 'Métodos de pago',
        'leyenda' => 'Leyendas de factura',
        'motivo' => 'Motivos de anulación',
        'evento' => 'Tipos de evento significativo',
        'unidad' => 'Unidades de medida',
    ];

    public static function lista(string $tipo)
    {
        return static::where('tipo', $tipo)->orderBy('codigo')->get();
    }

    public static function descripcionDe(string $tipo, string|int $codigo, ?string $default = null): ?string
    {
        $item = static::where('tipo', $tipo)->where('codigo', (string) $codigo)->first();

        return $item ? $item->descripcion : $default;
    }
}
