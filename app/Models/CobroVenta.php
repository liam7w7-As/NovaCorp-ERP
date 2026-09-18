<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobroVenta extends Model
{
    protected $fillable = [
        'venta_id',
        'cuota_venta_id',
        'comprobante_id',
        'user_id',
        'fecha',
        'monto',
        'metodo',
        'referencia',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CuotaVenta::class, 'cuota_venta_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
