<?php

namespace App\Models;

use Database\Factories\MensajeWhatsappFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsapp extends Model
{
    /** @use HasFactory<MensajeWhatsappFactory> */
    use HasFactory;

    protected $table = 'mensajes_whatsapp';

    protected $fillable = [
        'lead_id',
        'canal_whatsapp_id',
        'enviado_por_id',
        'meta_message_id',
        'meta_media_id',
        'direccion',
        'tipo',
        'contenido',
        'archivo_url',
        'mime_type',
        'nombre_archivo',
        'tamano_archivo',
        'payload',
        'estado',
        'ocurrio_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'ocurrio_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function canalWhatsapp(): BelongsTo
    {
        return $this->belongsTo(CanalWhatsapp::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }
}
