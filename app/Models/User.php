<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'activo', 'sucursal_id', 'punto_venta_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function puntoVenta()
    {
        return $this->belongsTo(PuntoVenta::class, 'punto_venta_id');
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function esSuperadmin(): bool
    {
        return $this->rol === Rol::OCULTO;
    }

    public function rolModelo()
    {
        return $this->belongsTo(Rol::class, 'rol', 'clave');
    }

    public function canalesWhatsapp(): BelongsToMany
    {
        return $this->belongsToMany(CanalWhatsapp::class, 'canal_whatsapp_user')->withTimestamps();
    }

    public function leadsAsignados(): HasMany
    {
        return $this->hasMany(Lead::class, 'vendedor_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }
}
