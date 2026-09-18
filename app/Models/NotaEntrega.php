<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaEntrega extends Model
{
    use Auditable;

    protected $fillable = [
        'numero',
        'venta_id',
        'sucursal_id',
        'user_id',
        'fecha',
        'estado',
        'cliente_nombre',
        'observaciones',
        'anulada_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'anulada_at' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleNotaEntrega::class);
    }

    public function getEstaAnuladaAttribute(): bool
    {
        return $this->estado === 'anulada';
    }
}
