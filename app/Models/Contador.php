<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contador extends Model
{
    protected $table = 'contadores';

    protected $fillable = ['clave', 'valor'];

    protected $casts = ['valor' => 'integer'];
}
