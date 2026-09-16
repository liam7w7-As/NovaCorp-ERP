<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Services\SiatService;
use App\Services\SucursalContext;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    public function index()
    {
        $sucursales = Sucursal::with(['puntosVenta' => fn ($q) => $q->orderBy('codigo')])
            ->orderBy('codigo')
            ->get();

        $sucursalActual = SucursalContext::sucursal();
        $puntoVentaActual = SucursalContext::puntoVenta();

        return view('sucursales.index', compact('sucursales', 'sucursalActual', 'puntoVentaActual'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => 'required|integer|min:0|unique:sucursales,codigo',
            'nombre' => 'required|string|max:150',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'municipio' => 'required|string|max:100',
        ]);

        $sucursal = Sucursal::create($data);

        // Crear automáticamente el Punto de Venta 0 (Principal) para la nueva sucursal
        PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'codigo' => 0,
            'nombre' => 'Caja Central / POS 0',
            'tipo_punto_venta' => 'Punto de Venta Fijo',
            'activo' => true,
        ]);

        return back()->with('exito', "Sucursal '{$sucursal->nombre}' creada con éxito.");
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $data = $request->validate([
            'codigo' => 'required|integer|min:0|unique:sucursales,codigo,'.$sucursal->id,
            'nombre' => 'required|string|max:150',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'municipio' => 'required|string|max:100',
            'activa' => 'nullable|boolean',
        ]);

        $data['activa'] = $request->has('activa');
        $sucursal->update($data);

        return back()->with('exito', "Sucursal '{$sucursal->nombre}' actualizada.");
    }

    public function destroy(Sucursal $sucursal)
    {
        if ($sucursal->codigo === 0) {
            return back()->with('error', 'No se puede eliminar la Casa Matriz (Código 0).');
        }

        if ($sucursal->facturas()->exists() || $sucursal->ventas()->exists()) {
            return back()->with('error', 'No se puede eliminar una sucursal con movimientos fiscales asociados. Puedes desactivarla.');
        }

        $sucursal->delete();

        return back()->with('exito', 'Sucursal eliminada.');
    }

    public function storePuntoVenta(Request $request, Sucursal $sucursal)
    {
        $data = $request->validate([
            'codigo' => 'required|integer|min:0',
            'nombre' => 'required|string|max:150',
            'tipo_punto_venta' => 'required|string|max:100',
        ]);

        $existe = PuntoVenta::where('sucursal_id', $sucursal->id)->where('codigo', $data['codigo'])->exists();
        if ($existe) {
            return back()->with('error', "El código de POS {$data['codigo']} ya existe en esta sucursal.");
        }

        PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'tipo_punto_venta' => $data['tipo_punto_venta'],
            'activo' => true,
        ]);

        return back()->with('exito', "Punto de Venta '{$data['nombre']}' registrado.");
    }

    public function updatePuntoVenta(Request $request, PuntoVenta $puntoVenta)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'tipo_punto_venta' => 'required|string|max:100',
            'activo' => 'nullable|boolean',
        ]);

        $data['activo'] = $request->has('activo');
        $puntoVenta->update($data);

        return back()->with('exito', 'Punto de Venta actualizado.');
    }

    public function solicitarCuis(PuntoVenta $puntoVenta, SiatService $siat)
    {
        $sucursal = $puntoVenta->sucursal;

        try {
            $res = $siat->solicitarCuis(
                (int) $sucursal->codigo,
                (int) $puntoVenta->codigo,
                $puntoVenta->id
            );

            if (! ($res['transaccion'] ?? false)) {
                return back()->with('error', 'El SIN rechazó la solicitud de CUIS: '.($res['codigoDescripcion'] ?? ''));
            }

            return back()->with('exito', "CUIS obtenido con éxito: {$res['cuis']}");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al solicitar CUIS: '.$e->getMessage());
        }
    }

    public function solicitarCufd(PuntoVenta $puntoVenta, SiatService $siat)
    {
        $sucursal = $puntoVenta->sucursal;

        if (! $puntoVenta->tieneCuisVigente()) {
            return back()->with('error', 'El Punto de Venta no tiene un CUIS vigente. Solicita primero el CUIS.');
        }

        try {
            $res = $siat->solicitarCufd(
                (int) $sucursal->codigo,
                (int) $puntoVenta->codigo,
                $puntoVenta->cuis,
                $puntoVenta->id
            );

            if (! ($res['transaccion'] ?? false)) {
                return back()->with('error', 'El SIN rechazó la solicitud de CUFD: '.($res['codigoDescripcion'] ?? ''));
            }

            return back()->with('exito', "CUFD obtenido con éxito (vigencia: {$res['fechaVigencia']})");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al solicitar CUFD: '.$e->getMessage());
        }
    }

    public function cambiarActiva(Request $request)
    {
        $data = $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'punto_venta_id' => 'nullable|exists:puntos_venta,id',
        ]);

        SucursalContext::setActiva((int) $data['sucursal_id'], isset($data['punto_venta_id']) ? (int) $data['punto_venta_id'] : null);

        return back()->with('exito', 'Sucursal activa cambiada a: '.SucursalContext::sucursal()->nombre.' · '.SucursalContext::puntoVenta()->nombre);
    }
}
