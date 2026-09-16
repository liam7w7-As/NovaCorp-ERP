<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaElectronica extends Model
{
    protected $table = 'facturas_electronicas';

    protected $fillable = [
        'venta_id', 'cuf', 'cufd', 'numero_factura', 'estado', 'fecha_emision',
        'xml_firmado', 'xml_respuesta', 'pdf_path', 'codigo_recepcion',
        'transaccion', 'leyenda', 'observaciones_sin', 'simulada', 'usuario_id',
        'tipo_emision', 'cafc', 'evento_id', 'en_paquete',
        'sucursal_id', 'punto_venta_id', 'codigo_sucursal', 'codigo_punto_venta',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'simulada' => 'boolean',
        'en_paquete' => 'boolean',
    ];

    public const ESTADOS = ['emitida', 'anulada', 'rechazada', 'pendiente', 'observada'];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

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

    public function notas()
    {
        return $this->hasMany(NotaFiscal::class, 'factura_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoContingencia::class, 'evento_id');
    }
}
