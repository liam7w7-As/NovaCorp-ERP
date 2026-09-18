<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Producto extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'codigo_interno',
        'codigo',
        'equivalente',
        'marca',
        'descripcion',
        'unidad',
        'codigo_sin',
        'unidad_sin',
        'costo',
        'precio',
        'stock',
        'stock_reservado',
        'stock_min',
        'ficha_tecnica_path',
        'ficha_tecnica_nombre',
        'ficha_tecnica_mime',
        'ficha_tecnica_tamano',
        'imagenes',
    ];

    protected $appends = [
        'ficha_tecnica_url',
        'imagenes_producto',
        'stock_disponible',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'precio' => 'decimal:2',
        'stock' => 'decimal:2',
        'stock_reservado' => 'decimal:2',
        'stock_min' => 'decimal:2',
        'imagenes' => 'array',
    ];

    /**
     * Generar código interno automático
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($producto) {

            if (! $producto->codigo_interno) {

                // Tomar iniciales de la descripción
                $nombre = strtoupper(trim($producto->descripcion));

                $palabras = explode(' ', $nombre);

                $prefijo = '';

                foreach ($palabras as $palabra) {

                    if (strlen($palabra) > 0) {

                        $prefijo .= substr($palabra, 0, 1);

                    }

                }

                // máximo 3 letras
                $prefijo = substr($prefijo, 0, 3);

                // siguiente número
                $numero = static::withTrashed()->count() + 1;

                $producto->codigo_interno =
                    $prefijo.'-'.str_pad($numero, 4, '0', STR_PAD_LEFT);

            }

        });

    }

    public function getEnAlertaAttribute(): bool
    {
        return $this->stock_disponible <= (float) $this->stock_min;
    }

    public function getStockDisponibleAttribute(): float
    {
        return round(max(0, (float) $this->stock - (float) $this->stock_reservado), 2);
    }

    public function getFichaTecnicaUrlAttribute(): ?string
    {
        if (! $this->ficha_tecnica_path) {
            return null;
        }

        return Storage::disk('public')->url($this->ficha_tecnica_path);
    }

    public function getImagenesProductoAttribute(): array
    {
        return collect($this->imagenes ?? [])
            ->filter(fn (array $imagen): bool => filled($imagen['path'] ?? null))
            ->map(fn (array $imagen): array => [
                ...$imagen,
                'url' => Storage::disk('public')->url($imagen['path']),
            ])
            ->values()
            ->all();
    }

    public function detalleCompras(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function detalleVentas(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function detalleProformas(): HasMany
    {
        return $this->hasMany(DetalleProforma::class);
    }

    public function detalleNotaEntregas(): HasMany
    {
        return $this->hasMany(DetalleNotaEntrega::class);
    }
}
