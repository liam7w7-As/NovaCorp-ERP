<?php

namespace App\Http\Controllers;

use App\Models\CobroVenta;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\CuotaVenta;
use App\Models\Venta;
use App\Services\ContadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function cuentas(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        // Sin filtro de modalidad: una venta de contado editada también puede
        // quedar con saldo, y debe poder cobrarse desde aquí.
        $baseCobrar = Venta::with(['cuotas' => fn ($query) => $query->orderBy('numero')])
            ->where('estado', 'activa')
            ->whereColumn('pagado', '<', 'total')
            ->orderBy('fecha');
        $basePagar = Compra::whereColumn('pagado', '<', 'total')
            ->orderBy('fecha');

        if ($q !== '') {
            $baseCobrar->where(function ($query) use ($q): void {
                $query->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%");
            });
            $basePagar->where(function ($query) use ($q): void {
                $query->where('numero', 'like', "%{$q}%")
                    ->orWhere('proveedor_nombre', 'like', "%{$q}%");
            });
        }

        $porCobrar = (clone $baseCobrar)->paginate(50, ['*'], 'cobrar_page');
        $porPagar = (clone $basePagar)->paginate(50, ['*'], 'pagar_page');

        $ventasActivasFiltradas = function ($query) use ($q): void {
            $query->where('estado', 'activa');

            if ($q !== '') {
                $query->where(function ($venta) use ($q): void {
                    $venta->where('numero', 'like', "%{$q}%")
                        ->orWhere('cliente_nombre', 'like', "%{$q}%");
                });
            }
        };

        return view('caja.cuentas', [
            'porCobrar' => $porCobrar,
            'porPagar' => $porPagar,
            'q' => $q,
            'totalCobrar' => round((float) (clone $baseCobrar)->sum(DB::raw('total - pagado')), 2),
            'totalPagar' => round((float) (clone $basePagar)->sum(DB::raw('total - pagado')), 2),
            'totalVencido' => round((float) CuotaVenta::whereIn('estado', ['pendiente', 'parcial'])
                ->whereDate('fecha_vencimiento', '<', now()->toDateString())
                ->whereHas('venta', $ventasActivasFiltradas)
                ->sum(DB::raw('monto - pagado')), 2),
            'porVencer' => CuotaVenta::whereIn('estado', ['pendiente', 'parcial'])
                ->whereBetween('fecha_vencimiento', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereHas('venta', $ventasActivasFiltradas)
                ->count(),
            'cobradoHoy' => round((float) CobroVenta::whereDate('fecha', now()->toDateString())
                ->when($q !== '', fn ($query) => $query->whereHas('venta', $ventasActivasFiltradas))
                ->sum('monto'), 2),
        ]);
    }

    public function cobrar(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'nullable|string|max:100',
            'fecha' => 'nullable|date',
            'cuota_id' => 'nullable|exists:cuota_ventas,id',
            'referencia' => 'nullable|string|max:120',
            'observaciones' => 'nullable|string|max:1000',
        ]);
        $monto = round((float) $data['monto'], 2);

        try {
            $cobro = DB::transaction(function () use ($venta, $monto, $data) {
                $bloqueada = Venta::whereKey($venta->getKey())->lockForUpdate()->firstOrFail();
                $saldo = $bloqueada->saldo;
                if ($bloqueada->estado !== 'activa' || $saldo <= 0) {
                    throw new InvalidArgumentException('Esta venta no tiene saldo pendiente.');
                }
                if ($monto > $saldo) {
                    throw new InvalidArgumentException("El monto supera el saldo pendiente (Bs {$saldo}).");
                }

                $this->asegurarPlanCuotas($bloqueada);
                $cuota = $this->aplicarCobroCuotas($bloqueada, $monto, $data['cuota_id'] ?? null);

                $fecha = $data['fecha'] ?? date('Y-m-d');
                $comp = $this->registrarPago(
                    tipo: 'ingreso',
                    concepto: "Cobro venta {$bloqueada->numero} — {$bloqueada->cliente_nombre}",
                    entidad: $bloqueada->cliente_nombre,
                    monto: $monto,
                    metodo: $data['metodo'] ?? 'Efectivo',
                    referencia: $bloqueada->numero,
                    fecha: $fecha,
                    origenVentaId: $bloqueada->id,
                );
                $bloqueada->increment('pagado', $monto);

                return CobroVenta::create([
                    'venta_id' => $bloqueada->id,
                    'cuota_venta_id' => $cuota?->id,
                    'comprobante_id' => $comp->id,
                    'user_id' => Auth::id(),
                    'fecha' => $fecha,
                    'monto' => $monto,
                    'metodo' => $data['metodo'] ?? 'Efectivo',
                    'referencia' => $data['referencia'] ?? null,
                    'observaciones' => $data['observaciones'] ?? null,
                ]);
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cuentas.recibo', $cobro)
            ->with('exito', 'Cobro registrado');
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
                    origenCompraId: $bloqueada->id,
                );
                $bloqueada->increment('pagado', $monto);
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Pago registrado');
    }

    public function recibo(CobroVenta $cobroVenta)
    {
        $cobroVenta->load(['venta', 'cuota', 'comprobante.pagos', 'usuario']);

        return view('caja.recibo', ['cobro' => $cobroVenta]);
    }

    protected function registrarPago(
        string $tipo,
        string $concepto,
        string $entidad,
        float $monto,
        string $metodo,
        string $referencia,
        ?string $fecha = null,
        ?int $origenVentaId = null,
        ?int $origenCompraId = null
    ): Comprobante {
        return DB::transaction(function () use ($tipo, $concepto, $entidad, $monto, $metodo, $referencia, $fecha, $origenVentaId, $origenCompraId) {
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
                'fecha' => $fecha ?? date('Y-m-d'),
                'hora' => now()->format('H:i'),
                'metodo' => $metodo,
                'referencia' => $referencia,
                'usuario_id' => Auth::id(),
                'origen_venta_id' => $origenVentaId,
                'origen_compra_id' => $origenCompraId,
            ]);
            $comp->pagos()->create([
                'forma_pago' => $metodo === 'Efectivo' ? 'efectivo' : 'otro',
                'monto' => round($monto, 2),
            ]);

            return $comp;
        });
    }

    protected function asegurarPlanCuotas(Venta $venta): void
    {
        if ($venta->cuotas()->exists()) {
            return;
        }

        $vencimiento = $venta->fecha_vencimiento
            ?: Carbon::parse($venta->fecha)->addDays((int) ($venta->credito_dias ?: 30));

        $venta->cuotas()->create([
            'numero' => 1,
            'fecha_vencimiento' => $vencimiento,
            'monto' => $venta->saldo,
            'pagado' => 0,
            'estado' => 'pendiente',
        ]);
    }

    protected function aplicarCobroCuotas(Venta $venta, float $monto, mixed $cuotaId): ?CuotaVenta
    {
        $restante = round($monto, 2);
        $principal = null;

        $query = CuotaVenta::where('venta_id', $venta->id)
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->lockForUpdate();

        if ($cuotaId) {
            $query->whereKey($cuotaId);
        }

        $cuotas = $query->orderBy('numero')->get();

        if ($cuotaId && $cuotas->isEmpty()) {
            throw new InvalidArgumentException('La cuota seleccionada no pertenece a esta venta o ya está pagada.');
        }

        if ($cuotaId && $restante > $cuotas->first()->saldo) {
            throw new InvalidArgumentException("El monto supera el saldo de la cuota seleccionada (Bs {$cuotas->first()->saldo}).");
        }

        if (! $cuotaId) {
            $cuotas = CuotaVenta::where('venta_id', $venta->id)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('fecha_vencimiento')
                ->orderBy('numero')
                ->lockForUpdate()
                ->get();
        }

        foreach ($cuotas as $cuota) {
            if ($restante <= 0) {
                break;
            }

            $aplicar = min($restante, $cuota->saldo);
            if ($aplicar <= 0) {
                continue;
            }

            if (! $principal) {
                $principal = $cuota;
            }

            $nuevoPagado = round((float) $cuota->pagado + $aplicar, 2);
            $cuota->update([
                'pagado' => $nuevoPagado,
                'estado' => $nuevoPagado >= (float) $cuota->monto ? 'pagada' : 'parcial',
            ]);
            $restante = round($restante - $aplicar, 2);
        }

        if ($restante > 0) {
            throw new InvalidArgumentException('No se pudo asignar todo el cobro a cuotas pendientes.');
        }

        return $principal;
    }
}
