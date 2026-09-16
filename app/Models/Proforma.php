<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proforma extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'fecha', 'validez', 'cliente_id', 'cliente_nombre',
        'estado', 'subtotal', 'descuento', 'total', 'nota', 'reserva_stock',
        'usuario_id', 'venta_id', 'contacto', 'telefono',
        'tiempo_entrega', 'condiciones_pago', 'garantia',
    ];

    protected $casts = [
        'fecha' => 'date',
        'validez' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'reserva_stock' => 'boolean',
    ];

    public const ESTADOS = ['borrador', 'enviada', 'aprobada', 'rechazada', 'vencida', 'convertida'];

    public const ESTADOS_CONVERTIBLES = ['enviada', 'aprobada'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleProforma::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function getEstaConvertidaAttribute(): bool
    {
        return $this->estado === 'convertida';
    }

    public function getEsConvertibleAttribute(): bool
    {
        return in_array($this->estado, self::ESTADOS_CONVERTIBLES, true);
    }
}
