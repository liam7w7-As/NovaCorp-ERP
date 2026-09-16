<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
    ];

    public static function get(string $clave, $default = null)
    {
        try {
            $row = static::where('clave', $clave)->first();

            return $row ? $row->valor : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $clave, $valor, ?string $tipo = null): self
    {
        return static::updateOrCreate(
            ['clave' => $clave],
            [
                'valor' => $valor,
            ] + ($tipo ? ['tipo' => $tipo] : [])
        );
    }

    public static function empresa(): array
    {
        return [

            'nombre' => static::get(
                'empresa_nombre',
                'GISECA SRL'
            ),

            'nit' => static::get(
                'empresa_nit',
                ''
            ),

            'direccion' => static::get(
                'empresa_direccion',
                'Av. Nestor Galindo esq. C. Manuel Cespedes Nº4482'
            ),

            'telefono' => static::get(
                'empresa_telefono',
                '77596739'
            ),

            'email' => static::get(
                'empresa_email',
                'ventas@giseca.com'
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LOGO DEL SISTEMA
    |--------------------------------------------------------------------------
    |
    | Se utiliza para:
    | - Login
    | - Menú lateral
    | - Dashboard
    | - Interfaz general
    |
    */

    public static function logo(): array
    {
        $path = static::get('logo_path');

        // Logo por defecto

        if (! $path) {

            return [

                'url' => asset(
                    'images/logo.png'
                ),

                'path' => 'images/logo.png',

                'tipo' => 'image',

            ];
        }

        return [

            'url' => asset(
                'storage/'.$path
            ),

            'path' => $path,

            'tipo' => 'image',

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBRETADO DOCUMENTOS
    |--------------------------------------------------------------------------
    |
    | Se utiliza para:
    | - Cotizaciones
    | - Proformas PDF
    | - Documentos empresariales
    |
    */

    public static function membretado(): array
    {
        $path = static::get(
            'membretado_path'
        );

        if (! $path) {

            return [

                'tipo' => 'image',
                'url' => null,
                'path' => null,

            ];
        }

        return [

            'tipo' => static::get(
                'membretado_tipo',
                'image'
            ),

            'url' => asset(
                'storage/'.$path
            ),

            'path' => $path,
        ];
    }
}
