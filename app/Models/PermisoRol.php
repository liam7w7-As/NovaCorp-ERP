<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermisoRol extends Model
{
    protected $table = 'permiso_rol';

    protected $fillable = ['rol_id', 'habilidad', 'permitido'];

    protected $casts = ['permitido' => 'boolean'];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }
}
