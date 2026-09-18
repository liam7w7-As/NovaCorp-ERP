<?php

namespace App\Services;

use App\Models\PermisoRol;
use App\Models\Rol;
use Illuminate\Support\Facades\Cache;

/**
 * Permisos por rol. Catálogo de habilidades + matriz en BD (tabla permiso_rol).
 * - superadmin: todo, siempre (oculto en la UI).
 * - admin: todo, siempre (fila bloqueada en la UI).
 * - demás roles: según matriz, cacheada 10 minutos.
 */
class Permisos
{
    /** habilidad => [módulo, descripción] */
    public const HABILIDADES = [
        'dashboard' => ['Panel', 'Ver el panel general'],
        'productos' => ['Inventario', 'Ver, crear y editar productos'],
        'clientes' => ['Comercial', 'Gestionar clientes'],
        'proveedores' => ['Compras', 'Gestionar proveedores'],
        'compras' => ['Compras', 'Crear y editar compras'],
        'almacen' => ['Inventario', 'Preparar y emitir notas de entrega'],
        'ventas' => ['Ventas', 'Crear y editar ventas'],
        'proformas' => ['Comercial', 'Gestionar proformas'],
        'crm' => ['Comercial', 'Gestionar leads y conversaciones asignadas'],
        'crm.administrar' => ['Comercial', 'Administrar líneas, asignaciones y todos los leads'],
        'facturas.ver' => ['Ventas', 'Ver facturas electrónicas'],
        'facturas.emitir' => ['Ventas', 'Emitir y anular facturas'],
        'comprobantes' => ['Contabilidad', 'Gestionar comprobantes y caja'],
        'finanzas' => ['Contabilidad', 'Gestionar ingresos y gastos'],
        'reportes' => ['Panel', 'Ver reportes'],
        'tributario' => ['Contabilidad', 'Ver módulo tributario'],
        'configuracion' => ['Sistema', 'Configurar el sistema'],
        'admin' => ['Sistema', 'Administrar usuarios, roles, papelera y respaldos'],
    ];

    /** Matriz inicial (se siembra en BD; la constante queda como respaldo). */
    public const MATRIZ = [
        'dashboard' => ['vendedor', 'contador'],
        'productos' => ['vendedor'],
        'clientes' => ['vendedor'],
        'proveedores' => ['contador'],
        'compras' => ['contador'],
        'almacen' => ['almacen', 'gerencia'],
        'ventas' => ['vendedor'],
        'proformas' => ['vendedor'],
        'crm' => ['vendedor', 'gerencia'],
        'crm.administrar' => ['gerencia'],
        'facturas.ver' => ['vendedor', 'contador'],
        'facturas.emitir' => ['vendedor'],
        'comprobantes' => ['contador'],
        'finanzas' => ['contador'],
        'reportes' => ['vendedor', 'contador'],
        'tributario' => ['contador'],
        'configuracion' => [],
        'admin' => [],
    ];

    public static function puede(?object $usuario, string $habilidad): bool
    {
        if (! $usuario) {
            return false;
        }
        $rol = $usuario->rol ?? null;
        if ($rol === Rol::OCULTO || $rol === 'admin') {
            return true;
        }

        return (bool) (self::mapaDelRol($rol)[$habilidad] ?? false);
    }

    public static function mapaDelRol(string $rol): array
    {
        return Cache::remember("permisos_rol_{$rol}", 600, function () use ($rol) {
            $id = Rol::where('clave', $rol)->value('id');
            if (! $id) {
                return [];
            }

            return PermisoRol::where('rol_id', $id)
                ->where('permitido', true)
                ->pluck('permitido', 'habilidad')
                ->toArray();
        });
    }

    public static function olvidarCache(?string $rol = null): void
    {
        if ($rol) {
            Cache::forget("permisos_rol_{$rol}");

            return;
        }
        foreach (Rol::pluck('clave') as $clave) {
            Cache::forget("permisos_rol_{$clave}");
        }
    }

    public static function habilidades(): array
    {
        return array_keys(self::HABILIDADES);
    }

    public static function esBloqueada(string $claveRol): bool
    {
        return in_array($claveRol, [Rol::OCULTO, 'admin'], true);
    }
}
