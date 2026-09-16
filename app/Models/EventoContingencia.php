<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventoContingencia extends Model
{
    protected $table = 'eventos_contingencia';

    protected $fillable = [
        'codigo_evento', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado',
        'codigo_recepcion_evento', 'paquete_path', 'codigo_recepcion_paquete',
        'estado_paquete', 'observaciones_sin', 'usuario_id',
        'sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    /** Códigos oficiales de eventos significativos del SIN */
    public const CODIGOS = [
        1 => 'Corte del servicio de internet',
        2 => 'Inaccesibilidad al servicio web de la administración tributaria',
        3 => 'Corte del suministro de energía eléctrica',
        4 => 'Falla del sistema informático de facturación',
        5 => 'Virus informático o ataque bloqueante',
        6 => 'Cambio de infraestructura o mantenimiento',
        7 => 'Otro evento significativo autorizado',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaElectronica::class, 'evento_id');
    }

    public function getEstaAbiertoAttribute(): bool
    {
        return $this->estado === 'abierto';
    }
}
