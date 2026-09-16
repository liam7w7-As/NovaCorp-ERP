<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PuntoVenta extends Model
{
    protected $table = 'puntos_venta';

    protected $fillable = [
        'sucursal_id',
        'codigo',
        'nombre',
        'tipo_punto_venta',
        'cuis',
        'cuis_vigencia',
        'cufd',
        'codigo_control',
        'cufd_vigencia',
        'activo',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'activo' => 'boolean',
        'cuis_vigencia' => 'datetime',
        'cufd_vigencia' => 'datetime',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'punto_venta_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaElectronica::class, 'punto_venta_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function tieneCuisVigente(): bool
    {
        if (empty($this->cuis)) {
            return false;
        }

        return ! $this->cuis_vigencia || $this->cuis_vigencia->isFuture();
    }

    public function tieneCufdVigente(): bool
    {
        if (empty($this->cufd)) {
            return false;
        }

        return ! $this->cufd_vigencia || $this->cufd_vigencia->isFuture();
    }
}
