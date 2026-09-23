<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'tipo', 'modalidad', 'proveedor_id', 'proveedor_nombre', 'fecha',
        'subtotal', 'descuento', 'descuento_tipo', 'total', 'pagado', 'base_cf', 'credito_fiscal',
        'observaciones', 'origen_siat', 'codigo_autorizacion',
        'numero_factura_siat', 'nit_proveedor', 'comprobante_numero',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'pagado' => 'decimal:2',
        'base_cf' => 'decimal:2',
        'credito_fiscal' => 'decimal:2',
        'origen_siat' => 'boolean',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_numero', 'numero');
    }

    public function getEsSiatAttribute(): bool
    {
        return (bool) $this->origen_siat;
    }

    public function getSaldoAttribute(): float
    {
        return round((float) $this->total - (float) $this->pagado, 2);
    }

    public function getEstaPagadaAttribute(): bool
    {
        return $this->saldo <= 0;
    }
}
