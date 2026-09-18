<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\NotaEntrega;
use App\Models\Venta;
use App\Services\ContadorService;
use App\Services\StockService;
use App\Services\SucursalContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        $ventasPendientes = Venta::with(['detalles.producto', 'sucursal'])
            ->where('estado', 'activa')
            ->where('origen_siat', false)
            ->whereIn('entrega_estado', ['pendiente', 'parcial'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%");
            }))
            ->orderByRaw("CASE entrega_estado WHEN 'parcial' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'ventas_page')
            ->withQueryString();

        $notas = NotaEntrega::with(['venta', 'usuario'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%")
                    ->orWhereHas('venta', fn ($venta) => $venta->where('numero', 'like', "%{$q}%"));
            }))
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'notas_page')
            ->withQueryString();

        $kpis = [
            'pendientes' => Venta::where('estado', 'activa')
                ->where('origen_siat', false)
                ->where('entrega_estado', 'pendiente')
                ->count(),
            'parciales' => Venta::where('estado', 'activa')
                ->where('origen_siat', false)
                ->where('entrega_estado', 'parcial')
                ->count(),
            'entregadas_hoy' => Venta::where('estado', 'activa')
                ->whereDate('entregado_at', now()->toDateString())
                ->count(),
            'notas_hoy' => NotaEntrega::whereDate('fecha', now()->toDateString())
                ->where('estado', 'emitida')
                ->count(),
        ];

        return view('almacen.index', compact('ventasPendientes', 'notas', 'kpis', 'q'));
    }

    public function show(Venta $venta)
    {
        SucursalContext::autorizaSucursal($venta->sucursal_id);

        $venta->load(['detalles.producto', 'notaEntregas.detalles', 'sucursal']);

        if ($venta->estado !== 'activa' || $venta->origen_siat) {
            return redirect()
                ->route('almacen.index')
                ->with('error', 'Solo se pueden preparar entregas de ventas activas con detalle de productos.');
        }

        return view('almacen.show', [
            'venta' => $venta,
            'fechaHoy' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, Venta $venta, ContadorService $contadores, StockService $stock)
    {
        SucursalContext::autorizaSucursal($venta->sucursal_id);

        $data = $request->validate([
            'fecha' => 'required|date',
            'cantidades' => 'required|array',
            'cantidades.*' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        try {
            $nota = DB::transaction(function () use ($data, $request, $venta, $contadores, $stock) {
                $ventaBloqueada = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
                if ($ventaBloqueada->estado !== 'activa' || $ventaBloqueada->origen_siat) {
                    throw new InvalidArgumentException('La venta no está disponible para entrega.');
                }

                $cantidades = collect($data['cantidades'])
                    ->mapWithKeys(fn ($cantidad, $id): array => [(int) $id => round((float) $cantidad, 2)])
                    ->filter(fn (float $cantidad): bool => $cantidad > 0);

                if ($cantidades->isEmpty()) {
                    throw new InvalidArgumentException('Indica al menos una cantidad a entregar.');
                }

                $detalles = DetalleVenta::with('producto')
                    ->where('venta_id', $ventaBloqueada->id)
                    ->whereIn('id', $cantidades->keys()->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($detalles->count() !== $cantidades->count()) {
                    throw new InvalidArgumentException('La entrega incluye productos que no pertenecen a la venta.');
                }

                foreach ($cantidades as $detalleId => $cantidad) {
                    $detalle = $detalles[$detalleId];
                    if ($cantidad > $detalle->pendiente_entrega) {
                        throw new InvalidArgumentException(
                            "La cantidad para {$detalle->codigo_producto} supera lo pendiente ({$detalle->pendiente_entrega})."
                        );
                    }
                }

                $nota = NotaEntrega::create([
                    'numero' => $contadores->siguienteUnico(
                        'NE-',
                        fn ($numero) => NotaEntrega::where('numero', $numero)->exists()
                    ),
                    'venta_id' => $ventaBloqueada->id,
                    'sucursal_id' => $ventaBloqueada->sucursal_id,
                    'user_id' => $request->user()?->id,
                    'fecha' => $data['fecha'],
                    'cliente_nombre' => $ventaBloqueada->cliente_nombre,
                    'observaciones' => $data['observaciones'] ?? null,
                ]);

                foreach ($cantidades as $detalleId => $cantidad) {
                    $detalle = $detalles[$detalleId];
                    if (! $detalle->producto) {
                        throw new InvalidArgumentException("El producto {$detalle->codigo_producto} ya no existe.");
                    }

                    $stock->entregarStockReservado($detalle->producto, $cantidad);

                    $detalle->increment('cantidad_entregada', $cantidad);

                    $nota->detalles()->create([
                        'detalle_venta_id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'codigo_producto' => $detalle->codigo_producto,
                        'descripcion_producto' => $detalle->descripcion_producto,
                        'cantidad' => $cantidad,
                    ]);
                }

                $this->sincronizarEstadoEntrega($ventaBloqueada->fresh('detalles'));

                return $nota->fresh(['detalles', 'venta']);
            });
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('almacen.notas.imprimir', $nota)
            ->with('exito', "Nota de entrega {$nota->numero} emitida.");
    }

    public function imprimir(NotaEntrega $notaEntrega)
    {
        $notaEntrega->load(['detalles', 'venta', 'sucursal', 'usuario']);

        return view('almacen.nota', ['nota' => $notaEntrega]);
    }

    public function anular(NotaEntrega $notaEntrega, StockService $stock)
    {
        if ($notaEntrega->estado === 'anulada') {
            return back()->with('error', 'La nota ya está anulada.');
        }

        try {
            DB::transaction(function () use ($notaEntrega, $stock) {
                $nota = NotaEntrega::whereKey($notaEntrega->id)->lockForUpdate()->firstOrFail();
                $nota->load(['detalles.producto', 'detalles.detalleVenta', 'venta.detalles']);

                if ($nota->venta?->estado !== 'activa') {
                    throw new InvalidArgumentException('Solo se puede anular una nota si la venta sigue activa.');
                }

                foreach ($nota->detalles as $detalleNota) {
                    if (! $detalleNota->producto || ! $detalleNota->detalleVenta) {
                        continue;
                    }

                    $cantidad = (float) $detalleNota->cantidad;
                    $stock->revertirEntregaAReserva($detalleNota->producto, $cantidad);
                    $detalleNota->detalleVenta->decrement('cantidad_entregada', $cantidad);
                }

                $nota->update([
                    'estado' => 'anulada',
                    'anulada_at' => now(),
                ]);

                $this->sincronizarEstadoEntrega($nota->venta->fresh('detalles'));
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Nota de entrega {$notaEntrega->numero} anulada.");
    }

    private function sincronizarEstadoEntrega(Venta $venta): void
    {
        $venta->loadMissing('detalles');

        $total = $venta->detalles->sum(fn (DetalleVenta $detalle): float => (float) $detalle->cantidad);
        $entregado = $venta->detalles->sum(fn (DetalleVenta $detalle): float => (float) $detalle->cantidad_entregada);

        $estado = 'pendiente';
        $entregadoAt = null;

        if ($total > 0 && $entregado >= $total) {
            $estado = 'entregada';
            $entregadoAt = now();
        } elseif ($entregado > 0) {
            $estado = 'parcial';
        }

        $venta->update([
            'entrega_estado' => $estado,
            'entregado_at' => $entregadoAt,
        ]);
    }
}
