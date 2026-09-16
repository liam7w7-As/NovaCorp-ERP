<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $fillable = [
        'usuario_id', 'usuario_nombre', 'accion', 'modelo',
        'modelo_id', 'descripcion', 'cambios', 'ip',
    ];

    protected $casts = ['cambios' => 'array'];

    public const ACCIONES = ['creado', 'actualizado', 'eliminado', 'restaurado'];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
