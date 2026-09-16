<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Venta;
use App\Services\ContadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->get('fecha', date('Y-m-d'));

        $base = Comprobante::with('pagos')->whereDate('fecha', $fecha)->orderByDesc('id');
        $movimientos = (clone $base)->paginate(30)->withQueryString();
        $ingresos = (float) (clone $base)->where('tipo', 'ingreso')->sum('monto');
        $egresos = (float) (clone $base)->where('tipo', 'egreso')->sum('monto');

        return view('caja.index', [
            'fecha' => $fecha,
            'movimientos' => $movimientos,
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'balance' => round($ingresos - $egresos, 2),
        ]);
    }

    public function cuentas()
    {
        $porCobrar = Venta::where('estado', 'activa')
            ->where('modalidad', 'credito')
            ->whereColumn('pagado', '<', 'total')
            ->orderBy('fecha')
            ->get();

        $porPagar = Compra::whereColumn('pagado', '<', 'total')
            ->orderBy('fecha')
            ->get();

        return view('caja.cuentas', [
            'porCobrar' => $porCobrar,
            'porPagar' => $porPagar,
            'totalCobrar' => round($porCobrar->sum(fn ($v) => $v->saldo), 2),
            'totalPagar' => round($porPagar->sum(fn ($c) => $c->saldo), 2),
        ]);
    }

    public function cobrar(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'nullable|string|max:100',
        ]);

        $saldo = $venta->saldo;
        if ($venta->estado !== 'activa' || $saldo <= 0) {
            return back()->with('error', 'Esta venta no tiene saldo pendiente.');
        }
        if ((float) $data['monto'] > $saldo) {
            return back()->with('error', "El monto supera el saldo pendiente (Bs {$saldo}).");
        }

        $this->registrarPago(
            tipo: 'ingreso',
            concepto: "Cobro venta {$venta->numero} — {$venta->cliente_nombre}",
            entidad: $venta->cliente_nombre,
            monto: (float) $data['monto'],
            metodo: $data['metodo'] ?? 'Efectivo',
            referencia: $venta->numero,
        );
        $venta->increment('pagado', round((float) $data['monto'], 2));

        return back()->with('exito', 'Cobro registrado');
    }

    public function pagar(Request $request, Compra $compra)
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'nullable|string|max:100',
        ]);

        $saldo = $compra->saldo;
        if ($saldo <= 0) {
            return back()->with('error', 'Esta compra no tiene saldo pendiente.');
        }
        if ((float) $data['monto'] > $saldo) {
            return back()->with('error', "El monto supera el saldo pendiente (Bs {$saldo}).");
        }

        $this->registrarPago(
            tipo: 'egreso',
            concepto: "Pago compra {$compra->numero} — {$compra->proveedor_nombre}",
            entidad: $compra->proveedor_nombre,
            monto: (float) $data['monto'],
            metodo: $data['metodo'] ?? 'Efectivo',
            referencia: $compra->numero,
        );
        $compra->increment('pagado', round((float) $data['monto'], 2));

        return back()->with('exito', 'Pago registrado');
    }

    protected function registrarPago(string $tipo, string $concepto, string $entidad, float $monto, string $metodo, string $referencia): void
    {
        DB::transaction(function () use ($tipo, $concepto, $entidad, $monto, $metodo, $referencia) {
            $contadores = app(ContadorService::class);
            $numero = $contadores->siguienteUnico(
                $tipo === 'ingreso' ? 'ING-' : 'EGR-',
                fn ($n) => Comprobante::withTrashed()->where('numero', $n)->exists()
            );

            $comp = Comprobante::create([
                'numero' => $numero,
                'tipo' => $tipo,
                'concepto' => $concepto,
                'entidad' => $entidad,
                'monto' => round($monto, 2),
                'fecha' => date('Y-m-d'),
                'hora' => now()->format('H:i'),
                'metodo' => $metodo,
                'referencia' => $referencia,
                'usuario_id' => Auth::id(),
            ]);
            $comp->pagos()->create([
                'forma_pago' => $metodo === 'Efectivo' ? 'efectivo' : 'otro',
                'monto' => round($monto, 2),
            ]);
        });
    }
}
