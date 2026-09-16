<?php

namespace App\Traits;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Registra created/updated/deleted/restored en la tabla auditorias.
 * Uso: use Auditable; en el modelo.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->auditar('creado'));
        static::updated(fn ($m) => $m->auditar('actualizado'));
        static::deleted(fn ($m) => $m->auditar('eliminado'));
        if (method_exists(static::class, 'restore')) {
            static::restored(fn ($m) => $m->auditar('restaurado'));
        }
    }

    public function auditar(string $accion): void
    {
        try {
            $cambios = null;
            if ($accion === 'actualizado') {
                $dirty = $this->getChanges();
                unset($dirty['updated_at']);
                $cambios = array_slice($dirty, 0, 20);
            } elseif ($accion === 'creado') {
                $cambios = array_slice($this->getAttributes(), 0, 20);
            }

            Auditoria::create([
                'usuario_id' => Auth::id(),
                'usuario_nombre' => Auth::user()->name ?? 'sistema',
                'accion' => $accion,
                'modelo' => class_basename($this),
                'modelo_id' => $this->getKey(),
                'descripcion' => $this->descripcionAuditoria(),
                'cambios' => $cambios,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe romper la operación, pero tampoco fallar en silencio
            Log::warning('Auditoría no registrada', ['error' => $e->getMessage()]);
        }
    }

    protected function descripcionAuditoria(): ?string
    {
        foreach (['numero', 'nombre', 'codigo', 'concepto', 'numero_factura'] as $campo) {
            if (! empty($this->{$campo})) {
                return (string) $this->{$campo};
            }
        }

        return null;
    }
}
