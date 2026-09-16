<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Venta;
use App\Services\ContadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        $monto = round((float) $data['monto'], 2);

        try {
            DB::transaction(function () use ($venta, $monto, $data) {
                $bloqueada = Venta::whereKey($venta->getKey())->lockForUpdate()->firstOrFail();
                $saldo = $bloqueada->saldo;
                if ($bloqueada->estado !== 'activa' || $saldo <= 0) {
                    throw new InvalidArgumentException('Esta venta no tiene saldo pendiente.');
                }
                if ($monto > $saldo) {
                    throw new InvalidArgumentException("El monto supera el saldo pendiente (Bs {$saldo}).");
                }

                $this->registrarPago(
                    tipo: 'ingreso',
                    concepto: "Cobro venta {$bloqueada->numero} — {$bloqueada->cliente_nombre}",
                    entidad: $bloqueada->cliente_nombre,
                    monto: $monto,
                    metodo: $data['metodo'] ?? 'Efectivo',
                    referencia: $bloqueada->numero,
                );
                $bloqueada->increment('pagado', $monto);
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Cobro registrado');
    }

    public function pagar(Request $request, Compra $compra)
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'nullable|string|max:100',
        ]);
        $monto = round((float) $data['monto'], 2);

        try {
            DB::transaction(function () use ($compra, $monto, $data) {
                $bloqueada = Compra::whereKey($compra->getKey())->lockForUpdate()->firstOrFail();
                $saldo = $bloqueada->saldo;
                if ($saldo <= 0) {
                    throw new InvalidArgumentException('Esta compra no tiene saldo pendiente.');
                }
                if ($monto > $saldo) {
                    throw new InvalidArgumentException("El monto supera el saldo pendiente (Bs {$saldo}).");
                }

                $this->registrarPago(
                    tipo: 'egreso',
                    concepto: "Pago compra {$bloqueada->numero} — {$bloqueada->proveedor_nombre}",
                    entidad: $bloqueada->proveedor_nombre,
                    monto: $monto,
                    metodo: $data['metodo'] ?? 'Efectivo',
                    referencia: $bloqueada->numero,
                );
                $bloqueada->increment('pagado', $monto);
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

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
