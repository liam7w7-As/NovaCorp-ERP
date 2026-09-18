<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleVenta extends Model
{
    protected $table = 'detalle_venta';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'codigo_interno',
        'codigo_producto',
        'descripcion_producto',
        'cantidad',
        'cantidad_entregada',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'cantidad_entregada' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function notaEntregaDetalles(): HasMany
    {
        return $this->hasMany(DetalleNotaEntrega::class);
    }

    public function getPendienteEntregaAttribute(): float
    {
        return round(max(0, (float) $this->cantidad - (float) $this->cantidad_entregada), 2);
    }

    public function getEstaEntregadoAttribute(): bool
    {
        return $this->pendiente_entrega <= 0;
    }
}
