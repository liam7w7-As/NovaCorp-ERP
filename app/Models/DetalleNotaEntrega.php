<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleNotaEntrega extends Model
{
    protected $table = 'detalle_nota_entregas';

    protected $fillable = [
        'nota_entrega_id',
        'detalle_venta_id',
        'producto_id',
        'codigo_producto',
        'descripcion_producto',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function notaEntrega(): BelongsTo
    {
        return $this->belongsTo(NotaEntrega::class);
    }

    public function detalleVenta(): BelongsTo
    {
        return $this->belongsTo(DetalleVenta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
