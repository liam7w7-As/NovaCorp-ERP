<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'codigo',
        'nombre',
        'direccion',
        'telefono',
        'municipio',
        'activa',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'activa' => 'boolean',
    ];

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class, 'sucursal_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'sucursal_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaElectronica::class, 'sucursal_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'sucursal_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeActivos($query)
    {
        return $query->where('activa', true);
    }

    public static function casaMatriz(): ?self
    {
        return static::where('codigo', 0)->first();
    }
}
