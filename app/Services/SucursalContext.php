<?php

namespace App\Services;

use App\Models\PuntoVenta;
use App\Models\Rol;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;

class SucursalContext
{
    public const SESSION_SUCURSAL_KEY = 'sucursal_activa_id';

    public const SESSION_POS_KEY = 'punto_venta_activo_id';

    /**
     * Devuelve la sucursal activa en la sesión o contexto del usuario.
     */
    public static function sucursal(): Sucursal
    {
        $id = session('sucursal_activa_id');

        if ($id && ($sucursal = Sucursal::where('activa', true)->find($id))) {
            return $sucursal;
        }

        $user = Auth::user();
        if ($user && $user->sucursal_id && ($sucursal = Sucursal::where('activa', true)->find($user->sucursal_id))) {
            session(['sucursal_activa_id' => $sucursal->id]);

            return $sucursal;
        }

        $casaMatriz = Sucursal::where('codigo', 0)->first()
            ?? Sucursal::firstOrCreate(
                ['codigo' => 0],
                ['nombre' => 'Casa Matriz', 'municipio' => 'Santa Cruz de la Sierra', 'activa' => true]
            );

        session(['sucursal_activa_id' => $casaMatriz->id]);

        return $casaMatriz;
    }

    /**
     * Devuelve el punto de venta activo para la sucursal actual.
     */
    public static function puntoVenta(): PuntoVenta
    {
        $sucursal = self::sucursal();
        $id = session('punto_venta_activo_id');

        if ($id && ($pv = PuntoVenta::where('sucursal_id', $sucursal->id)->where('activo', true)->find($id))) {
            return $pv;
        }

        $user = Auth::user();
        if ($user && $user->punto_venta_id && ($pv = PuntoVenta::where('sucursal_id', $sucursal->id)->where('activo', true)->find($user->punto_venta_id))) {
            session(['punto_venta_activo_id' => $pv->id]);

            return $pv;
        }

        $pos0 = PuntoVenta::where('sucursal_id', $sucursal->id)->where('codigo', 0)->first()
            ?? PuntoVenta::firstOrCreate(
                ['sucursal_id' => $sucursal->id, 'codigo' => 0],
                ['nombre' => 'Caja Central', 'tipo_punto_venta' => 'Punto de Venta Fijo', 'activo' => true]
            );

        session(['punto_venta_activo_id' => $pos0->id]);

        return $pos0;
    }

    /**
     * Establece la sucursal y punto de venta activos en la sesión.
     */
    public static function setActiva(int $sucursalId, ?int $puntoVentaId = null): void
    {
        $sucursal = Sucursal::findOrFail($sucursalId);
        session(['sucursal_activa_id' => $sucursal->id]);

        if ($puntoVentaId) {
            $pv = PuntoVenta::where('sucursal_id', $sucursal->id)->findOrFail($puntoVentaId);
            session(['punto_venta_activo_id' => $pv->id]);
        } else {
            $pv = PuntoVenta::where('sucursal_id', $sucursal->id)->where('codigo', 0)->first()
                ?? PuntoVenta::where('sucursal_id', $sucursal->id)->first();
            if ($pv) {
                session(['punto_venta_activo_id' => $pv->id]);
            } else {
                session()->forget('punto_venta_activo_id');
            }
        }
    }

    /**
     * Autoriza operar un documento de una sucursal: admin/superadmin siempre,
     * usuarios sin sucursal asignada por compatibilidad, el resto solo la suya.
     */
    public static function autorizaSucursal(?int $sucursalId): void
    {
        $usuario = Auth::user();
        if (! $usuario || ($usuario->rol ?? null) === Rol::OCULTO || ($usuario->rol ?? null) === 'admin') {
            return;
        }
        if (! $usuario->sucursal_id || ! $sucursalId) {
            return;
        }
        abort_if((int) $usuario->sucursal_id !== (int) $sucursalId, 403, 'Documento de otra sucursal.');
    }
}
