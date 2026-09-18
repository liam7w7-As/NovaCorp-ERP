@extends('layouts.app')

@section('title', 'Reportes')

@push('styles')
    <style>
        .reportes-section-title{border-bottom:2px solid var(--gc-primario);display:inline-flex;align-items:center;gap:8px;padding-bottom:6px;margin:0 0 16px;font-size:14px;font-weight:800;color:var(--gc-texto)}.reportes-section-title i{color:var(--gc-primario)}.reportes-kpis{display:grid;gap:14px;margin-bottom:22px}.reportes-kpis-5{grid-template-columns:repeat(5,minmax(0,1fr))}.reportes-kpis-4{grid-template-columns:repeat(4,minmax(0,1fr))}.reportes-kpis .kpi.rojo{border-top-color:var(--gc-rojo)}.reportes-header-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}.reportes-header-row form{margin:0}.reportes-alert-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:16px;margin-bottom:28px;align-items:start}.reportes-panel{padding:0;overflow:hidden}.reportes-panel-full{grid-column:1/-1}.reportes-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:14px 16px;border-bottom:1px solid var(--gc-borde)}.reportes-panel-head h6{font-size:14px;font-weight:800;margin:0}.reportes-panel-head span{display:block;margin-top:3px;font-size:11px;color:var(--gc-gris-claro)}.reportes-scroll{overflow-x:auto}.reportes-tabla-cobranza{min-width:620px}.reportes-tabla-entrega{min-width:520px}.reportes-tabla-productos{min-width:620px}.reportes-muted{font-size:10.5px;color:var(--gc-gris-claro);margin-top:3px}.reportes-empty{text-align:center;color:var(--gc-gris-claro);padding:34px}.reportes-chip{display:inline-flex;align-items:center;border-radius:999px;padding:4px 8px;font-size:10.5px;font-weight:800;white-space:nowrap}.reportes-chip-vencida{background:var(--gc-rojo-suave);color:var(--gc-rojo)}.reportes-chip-semana{background:var(--gc-amarillo-suave);color:#9a6400}.reportes-chip-parcial{background:var(--gc-info-suave);color:var(--gc-info)}.reportes-chip-pendiente{background:var(--gc-fondo);color:var(--gc-gris)}.reportes-chart-card{margin-bottom:0}.reportes-tabla-wrap{overflow-x:auto}.reportes-tabla-proformas{min-width:720px}@media(max-width:1100px){.reportes-alert-grid{grid-template-columns:1fr}.reportes-panel-full{grid-column:auto}.reportes-kpis-5{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:980px){.reportes-kpis-4{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.reportes-kpis,.reportes-kpis-4,.reportes-kpis-5{grid-template-columns:1fr}.reportes-header-row{display:grid;align-items:stretch}.reportes-header-row form,.reportes-header-row select{width:100% !important}.reportes-panel-head{display:grid}.reportes-chart-card{padding:14px}.reportes-chart-card canvas{min-height:220px}}
    </style>
@endpush

@section('content')
    <h6 class="reportes-section-title">
        <i class="bi bi-exclamation-triangle"></i> Alertas operativas
    </h6>

    <div class="reportes-kpis reportes-kpis-5">
        <div class="kpi azul">
            <div class="label"><i class="bi bi-cash-stack"></i> Total por cobrar</div>
            <div class="value">Bs {{ formatoMoneda($alertasOperativas['total_cobrar']) }}</div>
        </div>
        <div class="kpi rojo">
            <div class="label"><i class="bi bi-exclamation-circle"></i> Vencido</div>
            <div class="value">Bs {{ formatoMoneda($alertasOperativas['total_vencido']) }}</div>
        </div>
        <div class="kpi naranja">
            <div class="label"><i class="bi bi-calendar-week"></i> Por vencer 7 días</div>
            <div class="value">{{ $alertasOperativas['por_vencer'] }}</div>
        </div>
        <div class="kpi verde">
            <div class="label"><i class="bi bi-box-arrow-up"></i> Por entregar</div>
            <div class="value">{{ $alertasOperativas['entregas_pendientes'] }}</div>
        </div>
        <div class="kpi gris">
            <div class="label"><i class="bi bi-file-earmark-pdf"></i> Sin ficha técnica</div>
            <div class="value">{{ $alertasOperativas['productos_sin_ficha'] }}</div>
        </div>
    </div>

    <div class="reportes-alert-grid">
        <section class="card-giseca reportes-panel">
            <div class="reportes-panel-head">
                <div>
                    <h6>Cobranzas críticas</h6>
                    <span>Cuotas vencidas o con vencimiento dentro de 7 días.</span>
                </div>
                @can('comprobantes')
                    <a class="btn-giseca btn-outline btn-sm" href="{{ route('cuentas.index') }}">
                        <i class="bi bi-arrow-up-right"></i> Ver cuentas
                    </a>
                @endcan
            </div>
            <div class="reportes-scroll">
                <table class="tabla-giseca reportes-tabla-cobranza">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Cliente</th>
                            <th>Vence</th>
                            <th>Estado</th>
                            <th class="text-end">Saldo cuota</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cuotasCriticas as $cuota)
                            @php
                                $estaVencida = $cuota->esta_vencida;
                                $estadoClase = $estaVencida ? 'reportes-chip-vencida' : 'reportes-chip-semana';
                                $estadoTexto = $estaVencida ? 'Vencida' : 'Por vencer';
                            @endphp
                            <tr class="{{ $estaVencida ? 'alerta' : '' }}">
                                <td>
                                    <span class="codigo-chip">{{ $cuota->venta?->numero ?? 'Sin venta' }}</span>
                                    <div class="reportes-muted">Cuota #{{ $cuota->numero }}</div>
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($cuota->venta?->cliente_nombre ?? 'Sin cliente', 28) }}</td>
                                <td>{{ $cuota->fecha_vencimiento->format('Y-m-d') }}</td>
                                <td>
                                    <span class="reportes-chip {{ $estadoClase }}">{{ $estadoTexto }}</span>
                                    @if ($estaVencida)
                                        <div class="reportes-muted">{{ $cuota->dias_vencida }} día(s)</div>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">Bs {{ formatoMoneda($cuota->saldo) }}</td>
                                <td class="text-end">
                                    @can('comprobantes')
                                        @if ($cuota->venta)
                                            <a class="btn-giseca btn-primario btn-sm" href="{{ route('cuentas.index', ['q' => $cuota->venta->numero]) }}">
                                                <i class="bi bi-cash-coin"></i> Cobrar
                                            </a>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="reportes-empty">Sin cobranzas urgentes.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card-giseca reportes-panel">
            <div class="reportes-panel-head">
                <div>
                    <h6>Entregas pendientes</h6>
                    <span>Ventas activas que aún deben salir de almacén.</span>
                </div>
                @can('almacen')
                    <a class="btn-giseca btn-outline btn-sm" href="{{ route('almacen.index') }}">
                        <i class="bi bi-arrow-up-right"></i> Ver almacén
                    </a>
                @endcan
            </div>
            <div class="reportes-scroll">
                <table class="tabla-giseca reportes-tabla-entrega">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th class="text-end">Pendiente</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ventasEntrega as $venta)
                            <tr>
                                <td>
                                    <span class="codigo-chip">{{ $venta->numero }}</span>
                                    @if ($venta->sucursal)
                                        <div class="reportes-muted">{{ $venta->sucursal->nombre }}</div>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($venta->cliente_nombre, 28) }}</td>
                                <td>
                                    <span class="reportes-chip {{ $venta->entrega_estado === 'parcial' ? 'reportes-chip-parcial' : 'reportes-chip-pendiente' }}">
                                        {{ ucfirst($venta->entrega_estado) }}
                                    </span>
                                    <div class="reportes-muted">{{ $venta->porcentaje_entrega }}% entregado</div>
                                </td>
                                <td class="text-end fw-bold">{{ formatoMoneda($venta->cantidad_pendiente_entrega) }}</td>
                                <td class="text-end">
                                    @can('almacen')
                                        <a class="btn-giseca btn-primario btn-sm" href="{{ route('almacen.show', $venta) }}">
                                            <i class="bi bi-box-arrow-up"></i> Preparar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="reportes-empty">Sin entregas pendientes.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card-giseca reportes-panel reportes-panel-full">
            <div class="reportes-panel-head">
                <div>
                    <h6>Fichas por completar</h6>
                    <span>Productos sin PDF de respaldo técnico.</span>
                </div>
                @can('productos')
                    <a class="btn-giseca btn-outline btn-sm" href="{{ route('productos.index') }}">
                        <i class="bi bi-arrow-up-right"></i> Ver productos
                    </a>
                @endcan
            </div>
            <div class="reportes-scroll">
                <table class="tabla-giseca reportes-tabla-productos">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="text-end">Disponible</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productosSinFicha as $producto)
                            <tr>
                                <td><span class="codigo-chip">{{ $producto->codigo }}</span></td>
                                <td>
                                    <strong>{{ \Illuminate\Support\Str::limit($producto->descripcion, 30) }}</strong>
                                    <div class="reportes-muted">{{ $producto->marca ?: 'Sin marca' }}</div>
                                </td>
                                <td class="text-end fw-bold">{{ formatoMoneda($producto->stock_disponible) }}</td>
                                <td class="text-end">
                                    @can('productos')
                                        <a class="btn-giseca btn-outline btn-sm" href="{{ route('productos.index', ['q' => $producto->codigo]) }}">
                                            <i class="bi bi-pencil-square"></i> Completar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="reportes-empty">Todos los productos tienen ficha técnica.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <h6 class="reportes-section-title">
        <i class="bi bi-file-earmark-text"></i> Reporte de Proformas
    </h6>

    <div class="reportes-kpis reportes-kpis-4">
        <div class="kpi azul"><div class="label">Emitidas</div><div class="value">{{ $emitidas }}</div></div>
        <div class="kpi verde"><div class="label">Convertidas</div><div class="value">{{ $convertidas }}</div></div>
        <div class="kpi naranja"><div class="label">Tasa conversión</div><div class="value">{{ $tasa }}%</div></div>
        <div class="kpi gris"><div class="label">Valor cotizado</div><div class="value">Bs {{ formatoMoneda($valorTotal) }}</div></div>
    </div>

    <div class="card-giseca" style="margin-bottom:26px;">
        <div class="gc-panel-titulo" style="font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; margin-bottom:14px;"><i class="bi bi-file-earmark-text" style="color:var(--gc-primario);"></i> Detalle de Proformas</div>
        <div class="reportes-tabla-wrap">
            <table class="tabla-giseca reportes-tabla-proformas">
                <thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse($proformas as $p)
                        <tr><td><span class="codigo-chip">{{ $p->numero }}</span></td><td>{{ $p->fecha->format('Y-m-d') }}</td><td>{{ $p->cliente_nombre }}</td>
                            <td class="text-end">Bs {{ formatoMoneda($p->total) }}</td><td><span class="estado estado-{{ $p->estado }}">{{ strtoupper($p->estado) }}</span></td>
                            <td class="text-end"><a href="{{ route('proformas.show', $p) }}" class="btn-giseca btn-outline btn-icon btn-sm"><i class="bi bi-eye"></i></a></td></tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--gc-gris-claro); font-size:12.5px;">Sin proformas registradas todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="reportes-header-row">
        <h6 class="reportes-section-title" style="margin:0;">
            <i class="bi bi-bar-chart-line"></i> Resumen de Ventas (últimos 6 meses)
        </h6>

        <form method="GET" action="{{ route('reportes.index') }}">
            <select
                name="factura"
                class="form-control-giseca"
                onchange="this.form.submit()"
                style="width:200px;">
                <option value="todos" {{ ($facturaFiltro ?? 'todos') == 'todos' ? 'selected' : '' }}>
                    Todas las ventas
                </option>
                <option value="con" {{ ($facturaFiltro ?? '') == 'con' ? 'selected' : '' }}>
                    Con factura
                </option>
                <option value="sin" {{ ($facturaFiltro ?? '') == 'sin' ? 'selected' : '' }}>
                    Sin factura
                </option>
            </select>
        </form>
    </div>

    <div class="reportes-kpis reportes-kpis-4">
        <div class="kpi naranja"><div class="label"><i class="bi bi-cash-coin"></i> Total vendido</div><div class="value">Bs {{ formatoMoneda($kpiVentas['total']) }}</div></div>
        <div class="kpi verde"><div class="label"><i class="bi bi-wallet2"></i> Contado</div><div class="value">Bs {{ formatoMoneda($kpiVentas['contado']) }}</div></div>
        <div class="kpi azul"><div class="label"><i class="bi bi-hourglass-split"></i> Crédito</div><div class="value">Bs {{ formatoMoneda($kpiVentas['credito']) }}</div></div>
        <div class="kpi gris"><div class="label"><i class="bi bi-receipt"></i> Documentos</div><div class="value">{{ $kpiVentas['documentos'] }}</div></div>
    </div>

    <div class="card-giseca reportes-chart-card">
        <div class="gc-panel-titulo" style="font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; margin-bottom:14px;"><i class="bi bi-bar-chart-line" style="color:var(--gc-primario);"></i> Ventas mensuales</div>
        <canvas id="chartVentasMes" height="110"></canvas>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
try {
  new Chart(document.getElementById('chartVentasMes'), {
    type: 'bar',
    data: {
      labels: @json($etiquetas),
      datasets: [{ label: 'Ventas (Bs)', data: @json($ventasMes), backgroundColor: '#E8622C', borderRadius: 4 }],
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
  });
} catch (e) {
  document.getElementById('chartVentasMes').parentElement.innerHTML = '<div style="text-align:center; color:var(--gc-gris-claro); font-size:12.5px; padding:20px;"><i class="bi bi-wifi-off"></i> Gráfico no disponible sin conexión a internet.</div>';
}
</script>
@endpush
