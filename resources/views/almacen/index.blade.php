@extends('layouts.app')

@section('title', 'Almacén')

@section('content')
    <div class="almacen-kpis">
        <div class="kpi naranja">
            <div class="label"><i class="bi bi-hourglass-split"></i> Pendientes</div>
            <div class="value">{{ $kpis['pendientes'] }}</div>
        </div>
        <div class="kpi azul">
            <div class="label"><i class="bi bi-layers"></i> Parciales</div>
            <div class="value">{{ $kpis['parciales'] }}</div>
        </div>
        <div class="kpi verde">
            <div class="label"><i class="bi bi-check2-circle"></i> Entregadas hoy</div>
            <div class="value">{{ $kpis['entregadas_hoy'] }}</div>
        </div>
        <div class="kpi info">
            <div class="label"><i class="bi bi-clipboard-check"></i> Notas hoy</div>
            <div class="value">{{ $kpis['notas_hoy'] }}</div>
        </div>
    </div>

    <div class="almacen-toolbar">
        <form method="GET" action="{{ route('almacen.index') }}" class="almacen-search">
            <i class="bi bi-search"></i>
            <input name="q" class="form-control-giseca" value="{{ $q }}" placeholder="Venta, cliente o nota">
        </form>
    </div>

    @if (session('error'))
        <div class="alerta-giseca alerta-error">{{ session('error') }}</div>
    @endif
    @if (session('exito'))
        <div class="alerta-giseca alerta-ok">{{ session('exito') }}</div>
    @endif

    <div class="almacen-grid">
        <section class="card-giseca almacen-panel">
            <div class="almacen-panel-head">
                <h6>Pedidos por entregar</h6>
                <span>{{ $ventasPendientes->total() }}</span>
            </div>
            <div class="almacen-scroll">
                <table class="tabla-giseca almacen-tabla">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="text-end">Avance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ventasPendientes as $venta)
                            <tr>
                                <td>
                                    <span class="codigo-chip">{{ $venta->numero }}</span>
                                    @if ($venta->sucursal)
                                        <div class="almacen-muted">{{ $venta->sucursal->nombre }}</div>
                                    @endif
                                </td>
                                <td>{{ $venta->cliente_nombre }}</td>
                                <td>{{ $venta->fecha->format('Y-m-d') }}</td>
                                <td>
                                    <span class="estado {{ $venta->entrega_estado === 'parcial' ? 'estado-enviada' : 'estado-borrador' }}">
                                        {{ ucfirst($venta->entrega_estado) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <strong>{{ $venta->porcentaje_entrega }}%</strong>
                                    <div class="almacen-muted">{{ formatoMoneda($venta->cantidad_pendiente_entrega) }} pend.</div>
                                </td>
                                <td class="text-end">
                                    <a class="btn-giseca btn-primario btn-sm" href="{{ route('almacen.show', $venta) }}">
                                        <i class="bi bi-box-arrow-up"></i> Preparar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="almacen-empty">Sin pedidos pendientes.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($ventasPendientes->hasPages())
                <div class="almacen-paginacion">{{ $ventasPendientes->links() }}</div>
            @endif
        </section>

        <section class="card-giseca almacen-panel">
            <div class="almacen-panel-head">
                <h6>Notas de entrega</h6>
                <span>{{ $notas->total() }}</span>
            </div>
            <div class="almacen-scroll">
                <table class="tabla-giseca almacen-tabla">
                    <thead>
                        <tr>
                            <th>Nota</th>
                            <th>Venta</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notas as $nota)
                            <tr class="{{ $nota->estado === 'anulada' ? 'alerta' : '' }}">
                                <td><span class="codigo-chip">{{ $nota->numero }}</span></td>
                                <td>{{ $nota->venta?->numero ?? '—' }}</td>
                                <td>{{ $nota->cliente_nombre }}</td>
                                <td>{{ $nota->fecha->format('Y-m-d') }}</td>
                                <td>
                                    <span class="estado {{ $nota->estado === 'emitida' ? 'estado-aprobada' : 'estado-rechazada' }}">
                                        {{ ucfirst($nota->estado) }}
                                    </span>
                                </td>
                                <td class="text-end" style="white-space:nowrap;">
                                    <a class="btn-giseca btn-outline btn-icon btn-sm" title="Imprimir"
                                        href="{{ route('almacen.notas.imprimir', $nota) }}"><i class="bi bi-printer"></i></a>
                                    @if ($nota->estado === 'emitida')
                                        <form method="POST" action="{{ route('almacen.notas.anular', $nota) }}" style="display:inline;"
                                            data-confirm="¿Anular la nota {{ $nota->numero }}? La entrega volverá a quedar reservada."
                                            data-confirm-title="Anular nota de entrega" data-confirm-label="Anular nota" data-confirm-variant="peligro">
                                            @csrf
                                            <button class="btn-giseca btn-outline btn-icon btn-sm" title="Anular"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="almacen-empty">Sin notas de entrega.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($notas->hasPages())
                <div class="almacen-paginacion">{{ $notas->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .almacen-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.almacen-toolbar{display:flex;justify-content:flex-end;margin-bottom:14px}.almacen-search{position:relative;width:min(100%,360px)}.almacen-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--gc-gris-claro)}.almacen-search input{padding-left:34px}.almacen-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:16px}.almacen-panel{padding:0;overflow:hidden}.almacen-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--gc-borde)}.almacen-panel-head h6{margin:0;font-size:14px}.almacen-panel-head span{font-size:12px;color:var(--gc-gris);font-weight:700}.almacen-scroll{overflow-x:auto}.almacen-tabla{min-width:720px}.almacen-muted{font-size:10.5px;color:var(--gc-gris-claro);margin-top:3px}.almacen-empty{text-align:center;color:var(--gc-gris-claro);padding:36px}.alerta-giseca{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:12px}.alerta-error{background:var(--gc-rojo-suave);color:var(--gc-rojo)}.alerta-ok{background:var(--gc-verde-suave);color:var(--gc-verde)}.almacen-paginacion{padding:12px 16px;border-top:1px solid var(--gc-borde);overflow-x:auto}@media(max-width:1100px){.almacen-grid{grid-template-columns:1fr}.almacen-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.almacen-kpis{grid-template-columns:1fr}.almacen-toolbar{justify-content:stretch}.almacen-search{width:100%}}
    </style>
@endpush
