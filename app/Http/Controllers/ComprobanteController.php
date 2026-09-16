<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\Configuracion;
use App\Services\ComprobanteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComprobanteController extends Controller
{
    public function index(Request $request)
    {
        $query = Comprobante::with('pagos')->orderByDesc('id');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->get('tipo'));
        }
        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('concepto', 'like', "%{$q}%")
                    ->orWhere('entidad', 'like', "%{$q}%");
            });
        }

        $comprobantes = $query->paginate(20)->withQueryString();

        $kpis = [
            'ingresos' => (float) Comprobante::where('tipo', 'ingreso')->sum('monto'),
            'egresos' => (float) Comprobante::where('tipo', 'egreso')->sum('monto'),
        ];
        $kpis['balance'] = round($kpis['ingresos'] - $kpis['egresos'], 2);

        $seleccionado = null;
        if ($request->filled('ver')) {
            $seleccionado = Comprobante::with('pagos')->find($request->get('ver'));
        }

        return view('comprobantes.index', [
            'comprobantes' => $comprobantes,
            'kpis' => $kpis,
            'tipo' => $request->get('tipo', ''),
            'q' => $request->get('q', ''),
            'seleccionado' => $seleccionado,
        ]);
    }

    public function show(Comprobante $comprobante)
    {
        $comprobante->load('pagos');

        return view('comprobantes.show', [
            'comprobante' => $comprobante,
            'membretado' => Configuracion::membretado(),
        ]);
    }

    public function store(Request $request, ComprobanteService $comprobantes)
    {
        $data = $request->validate([
            'tipo' => 'required|in:ingreso,egreso',
            'entidad' => 'required|string|max:255',
            'concepto' => 'required|string|max:500',
            'monto' => 'required|numeric|min:0.01',
            'fecha' => 'required|date',
            'metodo' => 'nullable|string|max:100',
            'banco' => 'nullable|string|max:100',
            'cuenta' => 'nullable|string|max:100',
            'numero_referencia' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
        ]);

        $comp = $comprobantes->crearManual($data);

        return redirect()->route('comprobantes.index', ['ver' => $comp->id])
            ->with('exito', "Comprobante {$comp->numero} creado");
    }

    public function edit(Comprobante $comprobante)
    {
        $comprobante->load('pagos');

        return view('comprobantes.edit', compact('comprobante'));
    }

    public function update(Request $request, Comprobante $comprobante)
    {
        $esManual = $comprobante->es_manual;

        $data = $request->validate([
            'concepto' => 'required|string|max:500',
            'entidad' => 'required|string|max:255',
            'monto' => ($esManual ? 'required' : 'nullable').'|numeric|min:0.01',
            'fecha' => 'required|date',
            'referencia' => 'nullable|string|max:255',
            'nota' => 'nullable|string',
        ]);

        $compraOtraMonto = array_filter([
            'concepto' => $data['concepto'],
            'entidad' => $data['entidad'],
            'fecha' => $data['fecha'],
            'referencia' => $data['referencia'] ?? null,
            'nota' => $data['nota'] ?? null,
        ]);

        if ($esManual) {
            $compraOtraMonto['monto'] = $data['monto'];
        }

        DB::transaction(function () use ($comprobante, $compraOtraMonto, $esManual, $data) {
            $comprobante->update($compraOtraMonto);
            // Solo comprobantes manuales permiten cambiar el monto total
            if ($esManual && $comprobante->pagos()->count() === 1) {
                $comprobante->pagos()->update(['monto' => $data['monto']]);
            }
        });

        return redirect()->route('comprobantes.index', ['ver' => $comprobante->id])
            ->with('exito', 'Comprobante actualizado');
    }

    public function destroy(Comprobante $comprobante)
    {
        if (! $comprobante->es_manual) {
            return back()->with('error', 'No se puede eliminar: está ligado a una compra/venta. Elimina el documento origen.');
        }

        $comprobante->delete();

        return redirect()->route('comprobantes.index')->with('exito', 'Comprobante eliminado');
    }

    public function agregarPago(Request $request, Comprobante $comprobante)
    {
        $data = $request->validate([
            'forma_pago' => 'required|in:efectivo,transferencia,qr,tarjeta,cheque,otro',
            'monto' => 'required|numeric|min:0.01',
            'banco' => 'nullable|string|max:100',
            'cuenta' => 'nullable|string|max:100',
            'referencia' => 'nullable|string|max:100',
            'nota' => 'nullable|string|max:255',
        ]);

        $asignado = round((float) $comprobante->pagos()->sum('monto'), 2);
        if (round($asignado + (float) $data['monto'], 2) > round((float) $comprobante->monto, 2)) {
            return back()->with(
                'error',
                "Las formas de pago (Bs {$asignado} + Bs {$data['monto']}) superarían el monto del comprobante (Bs {$comprobante->monto})."
            );
        }

        $comprobante->pagos()->create([
            'forma_pago' => $data['forma_pago'],
            'monto' => $data['monto'],
            'banco' => $data['banco'] ?? null,
            'cuenta' => $data['cuenta'] ?? null,
            'referencia' => $data['referencia'] ?? null,
            'nota' => $data['nota'] ?? null,
        ]);

        // Si hay más de una forma de pago, el método principal pasa a "Múltiple"
        if ($comprobante->pagos()->count() > 1) {
            $comprobante->update(['metodo' => 'Múltiple']);
        }

        return back()->with('exito', 'Forma de pago agregada');
    }

    public function quitarPago(Comprobante $comprobante, int $pago)
    {
        $p = $comprobante->pagos()->findOrFail($pago);
        if ($comprobante->pagos()->count() <= 1) {
            return back()->with('error', 'El comprobante debe tener al menos una forma de pago.');
        }

        $p->delete();

        if ($comprobante->pagos()->count() === 1) {
            $unico = $comprobante->pagos()->first();
            $comprobante->update(['metodo' => ucfirst($unico->forma_pago)]);
        }

        return back()->with('exito', 'Forma de pago eliminada');
    }

    public function imprimir(Comprobante $comprobante)
    {
        $comprobante->load('pagos');

        return view('comprobantes.imprimir', [
            'comprobante' => $comprobante,
            'membretado' => Configuracion::membretado(),
        ]);
    }
}
