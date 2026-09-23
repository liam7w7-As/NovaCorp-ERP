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
        'credito_dias', 'credito_cuotas', 'fecha_vencimiento',
        'subtotal', 'descuento', 'descuento_tipo', 'total', 'pagado', 'base_df', 'debito_fiscal', 'estado',
        'entrega_estado', 'entregado_at',
        'observaciones', 'origen_siat', 'codigo_autorizacion',
        'numero_factura_siat', 'nit_cliente', 'comprobante_numero',
        'sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta',
        'lead_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'credito_dias' => 'integer',
        'credito_cuotas' => 'integer',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'pagado' => 'decimal:2',
        'base_df' => 'decimal:2',
        'debito_fiscal' => 'decimal:2',
        'origen_siat' => 'boolean',
        'entregado_at' => 'datetime',
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

    public function notaEntregas(): HasMany
    {
        return $this->hasMany(NotaEntrega::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaVenta::class);
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(CobroVenta::class);
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

    public function getProximaCuotaAttribute(): ?CuotaVenta
    {
        $cuotas = $this->relationLoaded('cuotas')
            ? $this->cuotas
            : $this->cuotas()->orderBy('numero')->get();

        return $cuotas
            ->filter(fn (CuotaVenta $cuota): bool => $cuota->saldo > 0)
            ->sortBy('fecha_vencimiento')
            ->first();
    }

    public function getTotalVencidoAttribute(): float
    {
        $cuotas = $this->relationLoaded('cuotas')
            ? $this->cuotas
            : $this->cuotas()->get();

        return round($cuotas
            ->filter(fn (CuotaVenta $cuota): bool => $cuota->esta_vencida)
            ->sum(fn (CuotaVenta $cuota): float => $cuota->saldo), 2);
    }

    public function getEstadoCobranzaAttribute(): string
    {
        if ($this->saldo <= 0) {
            return 'cobrada';
        }

        $proxima = $this->proxima_cuota;
        if (! $proxima) {
            return 'sin_plan';
        }

        if ($proxima->esta_vencida) {
            return 'vencida';
        }

        if ($proxima->fecha_vencimiento->betweenIncluded(now()->startOfDay(), now()->addDays(7)->endOfDay())) {
            return 'por_vencer';
        }

        return 'vigente';
    }

    public function getDiasVencidaAttribute(): int
    {
        $proxima = $this->proxima_cuota;

        return $proxima?->dias_vencida ?? 0;
    }

    public function getCantidadPendienteEntregaAttribute(): float
    {
        $detalles = $this->relationLoaded('detalles')
            ? $this->detalles
            : $this->detalles()->get(['cantidad', 'cantidad_entregada']);

        return round($detalles->sum(
            fn (DetalleVenta $detalle): float => max(
                0,
                (float) $detalle->cantidad - (float) $detalle->cantidad_entregada
            )
        ), 2);
    }

    public function getCantidadTotalEntregaAttribute(): float
    {
        $detalles = $this->relationLoaded('detalles')
            ? $this->detalles
            : $this->detalles()->get(['cantidad']);

        return round($detalles->sum(fn (DetalleVenta $detalle): float => (float) $detalle->cantidad), 2);
    }

    public function getPorcentajeEntregaAttribute(): int
    {
        $total = $this->cantidad_total_entrega;

        if ($total <= 0) {
            return 0;
        }

        return (int) round((($total - $this->cantidad_pendiente_entrega) / $total) * 100);
    }
}
