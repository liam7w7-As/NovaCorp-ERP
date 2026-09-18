@extends('layouts.app')

@section('title', 'Preparar Entrega')

@section('content')
    <div class="entrega-header">
        <div>
            <a href="{{ route('almacen.index') }}" class="btn-giseca btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Almacén</a>
            <h2>Venta {{ $venta->numero }}</h2>
            <div class="entrega-sub">{{ $venta->cliente_nombre }} · {{ $venta->fecha->format('Y-m-d') }}</div>
        </div>
        <div class="entrega-resumen">
            <span class="estado {{ $venta->entrega_estado === 'entregada' ? 'estado-aprobada' : ($venta->entrega_estado === 'parcial' ? 'estado-enviada' : 'estado-borrador') }}">
                {{ ucfirst($venta->entrega_estado) }}
            </span>
            <strong>{{ $venta->porcentaje_entrega }}%</strong>
        </div>
    </div>

    @if (session('error'))
        <div class="alerta-giseca alerta-error">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alerta-giseca alerta-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('almacen.entregar', $venta) }}" class="card-giseca entrega-card">
        @csrf
        <div class="entrega-form-grid">
            <div>
                <label class="form-label-giseca">Fecha</label>
                <input type="date" name="fecha" class="form-control-giseca" value="{{ old('fecha', $fechaHoy) }}" required>
            </div>
            <div>
                <label class="form-label-giseca">Observaciones</label>
                <input name="observaciones" class="form-control-giseca" value="{{ old('observaciones') }}">
            </div>
        </div>

        <div class="entrega-scroll">
            <table class="tabla-giseca entrega-tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th class="text-end">Pedido</th>
                        <th class="text-end">Entregado</th>
                        <th class="text-end">Pendiente</th>
                        <th class="text-end">Stock disp.</th>
                        <th class="text-end">Entregar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($venta->detalles as $detalle)
                        @php
                            $pendiente = $detalle->pendiente_entrega;
                            $disponible = $detalle->producto?->stock_disponible ?? 0;
                        @endphp
                        <tr class="{{ $pendiente <= 0 ? 'entrega-linea-ok' : '' }}">
                            <td><span class="codigo-chip">{{ $detalle->codigo_producto }}</span></td>
                            <td>
                                {{ $detalle->descripcion_producto }}
                                @if ($detalle->producto)
                                    <div class="entrega-muted">Físico {{ formatoMoneda($detalle->producto->stock) }} · Reservado {{ formatoMoneda($detalle->producto->stock_reservado) }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ formatoMoneda($detalle->cantidad) }}</td>
                            <td class="text-end">{{ formatoMoneda($detalle->cantidad_entregada) }}</td>
                            <td class="text-end"><strong>{{ formatoMoneda($pendiente) }}</strong></td>
                            <td class="text-end">{{ formatoMoneda($disponible) }}</td>
                            <td class="text-end">
                                <input type="number" name="cantidades[{{ $detalle->id }}]" class="form-control-giseca entrega-input"
                                    step="0.01" min="0" max="{{ $pendiente }}" value="{{ $pendiente > 0 ? number_format($pendiente, 2, '.', '') : '0' }}"
                                    {{ $pendiente <= 0 ? 'disabled' : '' }}>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="entrega-actions">
            <button class="btn-giseca btn-primario" {{ $venta->cantidad_pendiente_entrega <= 0 ? 'disabled' : '' }}>
                <i class="bi bi-clipboard-check"></i> Emitir nota
            </button>
            <a href="{{ route('ventas.index', ['q' => $venta->numero]) }}" class="btn-giseca btn-outline">Ver venta</a>
        </div>
    </form>

    <section class="card-giseca entrega-notas">
        <h6>Notas emitidas</h6>
        <div class="entrega-scroll">
            <table class="tabla-giseca entrega-tabla notas">
                <thead>
                    <tr>
                        <th>Nota</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th class="text-end">Items</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($venta->notaEntregas as $nota)
                        <tr class="{{ $nota->estado === 'anulada' ? 'alerta' : '' }}">
                            <td><span class="codigo-chip">{{ $nota->numero }}</span></td>
                            <td>{{ $nota->fecha->format('Y-m-d') }}</td>
                            <td>{{ ucfirst($nota->estado) }}</td>
                            <td class="text-end">{{ $nota->detalles->count() }}</td>
                            <td class="text-end">
                                <a class="btn-giseca btn-outline btn-icon btn-sm" href="{{ route('almacen.notas.imprimir', $nota) }}"><i class="bi bi-printer"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="entrega-empty">Sin notas emitidas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('styles')
    <style>
        .entrega-header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:16px}.entrega-header h2{margin:10px 0 4px;font-size:24px}.entrega-sub,.entrega-muted{font-size:12px;color:var(--gc-gris)}.entrega-resumen{display:flex;align-items:center;gap:10px;background:var(--gc-superficie);border:1px solid var(--gc-borde);border-radius:7px;padding:10px 12px}.entrega-card{padding:16px}.entrega-form-grid{display:grid;grid-template-columns:180px minmax(0,1fr);gap:12px;margin-bottom:14px}.entrega-scroll{overflow-x:auto}.entrega-tabla{min-width:860px}.entrega-input{width:110px;text-align:right;margin-left:auto}.entrega-linea-ok{opacity:.62}.entrega-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:14px;flex-wrap:wrap}.entrega-notas{padding:16px;margin-top:16px}.entrega-notas h6{margin:0 0 12px}.entrega-tabla.notas{min-width:560px}.entrega-empty{text-align:center;color:var(--gc-gris-claro);padding:28px}.alerta-giseca{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:12px}.alerta-error{background:var(--gc-rojo-suave);color:var(--gc-rojo)}@media(max-width:760px){.entrega-header{display:grid}.entrega-form-grid{grid-template-columns:1fr}.entrega-resumen{justify-content:space-between}.entrega-actions{justify-content:stretch}.entrega-actions .btn-giseca{flex:1}}
    </style>
@endpush
