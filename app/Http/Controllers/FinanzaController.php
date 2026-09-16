<?php

namespace App\Http\Controllers;

use App\Models\MovimientoCaja;
use Illuminate\Http\Request;

class FinanzaController extends Controller
{
    public function index(Request $request)
    {

        $query = MovimientoCaja::query();

        // FILTRO TIPO
        if ($request->filled('tipo') && $request->tipo != 'todos') {

            $query->where('tipo', $request->tipo);
        }

        // FILTRO FECHA DESDE
        if ($request->filled('desde')) {

            $query->whereDate(
                'fecha',
                '>=',
                $request->desde
            );
        }

        // FILTRO FECHA HASTA
        if ($request->filled('hasta')) {

            $query->whereDate(
                'fecha',
                '<=',
                $request->hasta
            );
        }

        $movimientos = $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(50);

        // KPIs generales

        $ingresos = MovimientoCaja::where('tipo', 'ingreso')
            ->sum('importe');

        $gastos = MovimientoCaja::where('tipo', 'gasto')
            ->sum('importe');

        $balance = $ingresos - $gastos;

        return view('finanzas.index', [

            'movimientos' => $movimientos,

            'ingresos' => $ingresos,

            'gastos' => $gastos,

            'balance' => $balance,

            'filtros' => $request->all(),

        ]);
    }

    public function store(Request $request)
    {

        $data = $request->validate([

            'tipo' => 'required|in:ingreso,gasto',

            'fecha' => 'required|date',

            'concepto' => 'required|string|max:255',

            'importe' => 'required|numeric|min:0.01',

            'detalle' => 'nullable|string',

            'observaciones' => 'nullable|string',

        ]);

        MovimientoCaja::create([

            'fecha' => $data['fecha'],

            'tipo' => $data['tipo'],

            'concepto' => $data['concepto'],

            'detalle' => $data['detalle'] ?? null,

            'importe' => $data['importe'],

            'origen' => 'manual',

            'observaciones' => $data['observaciones'] ?? null,

            'usuario_id' => auth()->id(),

        ]);

        return back()->with(
            'exito',
            'Movimiento registrado correctamente'
        );
    }
}
