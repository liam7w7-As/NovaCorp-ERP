<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comprobante extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'tipo', 'concepto', 'entidad', 'monto', 'nota',
        'fecha', 'hora', 'metodo', 'referencia', 'usuario_id',
        'origen_compra_id', 'origen_venta_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(ComprobantePago::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compraOrigen(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'origen_compra_id');
    }

    public function ventaOrigen(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'origen_venta_id');
    }

    public function getEsManualAttribute(): bool
    {
        return is_null($this->origen_compra_id) && is_null($this->origen_venta_id);
    }

    public function getTotalAsignadoAttribute(): float
    {
        return round($this->pagos->sum(fn ($p) => (float) $p->monto), 2);
    }
}
