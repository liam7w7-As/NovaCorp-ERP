<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'tipo', 'modalidad', 'cliente_id', 'cliente_nombre', 'fecha',
        'subtotal', 'descuento', 'total', 'pagado', 'base_df', 'debito_fiscal', 'estado',
        'observaciones', 'origen_siat', 'codigo_autorizacion',
        'numero_factura_siat', 'nit_cliente', 'comprobante_numero',
        'sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta',
        'lead_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'pagado' => 'decimal:2',
        'base_df' => 'decimal:2',
        'debito_fiscal' => 'decimal:2',
        'origen_siat' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_numero', 'numero');
    }

    public function facturaElectronica(): HasOne
    {
        return $this->hasOne(FacturaElectronica::class);
    }

    public function getTieneFacturaAttribute(): bool
    {
        return $this->facturaElectronica()->where('estado', '!=', 'rechazada')->exists();
    }

    public function getEsSiatAttribute(): bool
    {
        return (bool) $this->origen_siat;
    }

    public function getEstaAnuladaAttribute(): bool
    {
        return $this->estado === 'anulada';
    }

    public function getSaldoAttribute(): float
    {
        return round((float) $this->total - (float) $this->pagado, 2);
    }

    public function getEstaCobradaAttribute(): bool
    {
        return $this->saldo <= 0;
    }
}
