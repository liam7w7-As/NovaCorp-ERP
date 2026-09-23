<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\CanalWhatsappFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CanalWhatsapp extends Model
{
    /** @use HasFactory<CanalWhatsappFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'canal_whatsapps';

    protected $fillable = [
        'nombre',
        'ciudad',
        'telefono',
        'telefono_normalizado',
        'waba_id',
        'phone_number_id',
        'meta_display_phone_number',
        'meta_verified_name',
        'meta_quality_rating',
        'meta_code_verification_status',
        'access_token',
        'graph_version',
        'plantilla_nombre',
        'plantilla_idioma',
        'estado',
        'webhook_suscrito_at',
        'meta_verificado_at',
        'ultimo_error',
        'ultimo_vendedor_asignado_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'access_token' => 'encrypted',
            'webhook_suscrito_at' => 'datetime',
            'meta_verificado_at' => 'datetime',
        ];
    }

    public function vendedores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'canal_whatsapp_user')->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function ultimoVendedorAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_vendedor_asignado_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
