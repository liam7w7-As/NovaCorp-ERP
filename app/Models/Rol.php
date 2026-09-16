<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['clave', 'nombre', 'descripcion', 'es_sistema'];

    protected $casts = ['es_sistema' => 'boolean'];

    public const OCULTO = 'superadmin';

    public function permisos(): HasMany
    {
        return $this->hasMany(PermisoRol::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol', 'clave');
    }

    public function scopeVisibles($query)
    {
        return $query->where('clave', '!=', self::OCULTO);
    }
}
