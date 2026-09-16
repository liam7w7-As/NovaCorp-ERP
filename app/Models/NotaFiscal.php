<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaFiscal extends Model
{
    protected $table = 'notas_fiscales';

    protected $fillable = [
        'factura_id', 'tipo', 'numero', 'monto', 'motivo',
        'estado', 'cuf', 'codigo_recepcion', 'simulada', 'usuario_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'simulada' => 'boolean',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaElectronica::class, 'factura_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
