<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'etapa_crm_id',
        'canal_whatsapp_id',
        'vendedor_id',
        'cliente_id',
        'nombre',
        'telefono',
        'telefono_normalizado',
        'correo',
        'empresa',
        'ciudad',
        'origen',
        'tipo_consulta',
        'valor_estimado',
        'notas',
        'ultima_interaccion_at',
        'etapa_actualizada_at',
        'cerrado_at',
        'motivo_perdida',
    ];

    protected function casts(): array
    {
        return [
            'valor_estimado' => 'decimal:2',
            'ultima_interaccion_at' => 'datetime',
            'etapa_actualizada_at' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(EtapaCrm::class, 'etapa_crm_id');
    }

    public function canalWhatsapp(): BelongsTo
    {
        return $this->belongsTo(CanalWhatsapp::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function mensajesWhatsapp(): HasMany
    {
        return $this->hasMany(MensajeWhatsapp::class)->orderBy('ocurrio_at');
    }

    public function ultimoMensajeWhatsapp(): HasOne
    {
        return $this->hasOne(MensajeWhatsapp::class)->latestOfMany('id');
    }
}
