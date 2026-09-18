<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuotaVenta extends Model
{
    protected $fillable = [
        'venta_id',
        'numero',
        'fecha_vencimiento',
        'monto',
        'pagado',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'pagado' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(CobroVenta::class);
    }

    public function getSaldoAttribute(): float
    {
        return round((float) $this->monto - (float) $this->pagado, 2);
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->saldo > 0 && $this->fecha_vencimiento->isPast() && ! $this->fecha_vencimiento->isToday();
    }

    public function getDiasVencidaAttribute(): int
    {
        if (! $this->esta_vencida) {
            return 0;
        }

        return $this->fecha_vencimiento->diffInDays(now());
    }
}
