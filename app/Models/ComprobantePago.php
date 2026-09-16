<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobantePago extends Model
{
    protected $table = 'comprobante_pagos';

    protected $fillable = [
        'comprobante_id', 'forma_pago', 'banco', 'cuenta', 'referencia', 'nota', 'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
