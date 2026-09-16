<?php

namespace App\Models;

use Database\Factories\EtapaCrmFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtapaCrm extends Model
{
    /** @use HasFactory<EtapaCrmFactory> */
    use HasFactory;

    public const ENTRADA = 'menu';

    public const NUEVO = self::ENTRADA;

    public const GANADA = 'ganada';

    public const PERDIDA = 'perdida';

    protected $table = 'etapas_crm';

    protected $fillable = [
        'nombre',
        'slug',
        'orden',
        'color',
        'tipo',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true)->orderBy('orden');
    }

    public static function inicial(): self
    {
        return static::query()->where('slug', self::NUEVO)->firstOrFail();
    }
}
