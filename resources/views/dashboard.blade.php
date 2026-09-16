@extends('layouts.app')

@section('title', 'Panel General')

@push('styles')
<style>
  .gc-panel-titulo{ font-size:14px; font-weight:700; color:var(--gc-texto); display:flex; align-items:center; gap:8px; margin-bottom:14px; }
  .gc-panel-titulo i{ color:var(--gc-primario); font-size:15px; }
  .gc-fila-lista{ display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid var(--gc-borde); font-size:13px; }
  .gc-fila-lista:last-child{ border-bottom:none; }
  .gc-fila-lista .icono{ width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:13px; margin-right:10px; }
  .gc-vacio{ text-align:center; padding:26px 10px; color:var(--gc-gris-claro); font-size:12.5px; }
  .gc-vacio i{ font-size:26px; display:block; margin-bottom:8px; opacity:.5; }
  .gc-kpi-icono{ width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; color:#fff; margin-bottom:10px; }
  .gc-variacion{ font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:3px; margin-top:4px; }
  .gc-variacion.subio{ color:var(--gc-verde); }
  .gc-variacion.bajo{ color:var(--gc-rojo); }
  .gc-variacion.neutro{ color:var(--gc-gris-claro); }
  .gc-encabezado-informe{ display:none; }
  .gc-seccion-titulo{ font-size:16px; font-weight:800; color:var(--gc-texto); margin:26px 0 14px; padding-bottom:8px; border-bottom:2px solid var(--gc-primario); display:flex; align-items:center; justify-content:space-between; }
  @media print{
    .gc-encabezado-informe{ display:block !important; text-align:center; margin-bottom:20px; }
    .gc-encabezado-informe .titulo{ font-size:20px; font-weight:800; }
    .gc-encabezado-informe .fecha{ font-size:12px; color:var(--gc-gris); }
    .card-giseca{ break-inside:avoid; }
  }
</style>
@endpush

@section('content')
<div class="gc-encabezado-informe">
  <div class="titulo">GISECA SRL — Informe Ejecutivo</div>
  <div class="fecha">Generado el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</div>
</div>

<div class="no-imprimir" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('dashboard') }}" style="margin:0; display:flex; gap:8px; align-items:center;">
    <label class="form-label-giseca" style="margin:0;">Mes:</label>
    <input type="month" name="mes" class="form-control-giseca" style="width:170px;" value="{{ $mes }}" onchange="this.form.submit()">
  </form>
  <div style="display:flex; gap:8px;">
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('dashboard.exportar', ['mes' => $mes]) }}"><i class="bi bi-download"></i> Exportar CSV</a>
    <button class="btn-giseca btn-oscuro" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir Informe para Socios</button>
  </div>
</div>

<!-- ===== KPIs principales con variación vs mes anterior ===== -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:8px;">
  <div class="kpi azul">
    <div class="gc-kpi-icono" style="background:var(--gc-info);"><i class="bi bi-cash-coin"></i></div>
    <div class="label">Ventas del mes</div><div class="value">Bs {{ formatoMoneda($totalVentasMes) }}</div>
    <div class="gc-variacion {{ $varVentas['clase'] }}"><i class="bi bi-{{ $varVentas['icono'] }}"></i> {{ $varVentas['texto'] }}</div>
  </div>
  <div class="kpi naranja">
    <div class="gc-kpi-icono" style="background:var(--gc-primario);"><i class="bi bi-cart-plus-fill"></i></div>
    <div class="label">Compras del mes</div><div class="value">Bs {{ formatoMoneda($totalComprasMes) }}</div>
    <div class="gc-variacion {{ $varCompras['clase'] }}"><i class="bi bi-{{ $varCompras['icono'] }}"></i> {{ $varCompras['texto'] }}</div>
  </div>
  <div class="kpi verde">
    <div class="gc-kpi-icono" style="background:var(--gc-verde);"><i class="bi bi-graph-up"></i></div>
    <div class="label">Utilidad bruta del mes</div><div class="value">Bs {{ formatoMoneda($utilidadMes) }}</div>
    <div class="gc-variacion {{ $varUtilidad['clase'] }}"><i class="bi bi-{{ $varUtilidad['icono'] }}"></i> {{ $varUtilidad['texto'] }}</div>
  </div>
  <div class="kpi gris">
    <div class="gc-kpi-icono" style="background:var(--gc-gris);"><i class="bi bi-arrow-left-right"></i></div>
    <div class="label">Flujo de caja del mes</div><div class="value">Bs {{ formatoMoneda($flujoMes) }}</div>
    <div class="gc-variacion neutro">Ing. Bs {{ formatoMoneda($ingresosMes) }} · Egr. Bs {{ formatoMoneda($egresosMes) }}</div>
  </div>
</div>

<!-- ===== Flujo de caja y ventas vs compras ===== -->
<div class="gc-seccion-titulo"><span><i class="bi bi-graph-up-arrow" style="color:var(--gc-primario);"></i> Desempeño Financiero</span></div>
<div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px;">
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-bar-chart-line"></i> Ventas vs. Compras — últimos 6 meses</div>
    <canvas id="chartVentasCompras" height="110"></canvas>
  </div>
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-cash-stack"></i> Flujo de Caja — Ingresos vs. Egresos</div>
    <canvas id="chartFlujoCaja" height="110"></canvas>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px;">
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-graph-up"></i> Utilidad Mensual</div>
    <canvas id="chartUtilidad" height="110"></canvas>
  </div>
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-pie-chart-fill"></i> Ventas por Marca</div>
    <canvas id="chartMarca" height="110"></canvas>
  </div>
</div>

<!-- ===== Rankings ===== -->
<div class="gc-seccion-titulo"><span><i class="bi bi-trophy-fill" style="color:var(--gc-primario);"></i> Rankings del Periodo</span></div>
<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:18px; margin-bottom:18px;">
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-star-fill"></i> Mejores Clientes</div>
    @forelse($topClientes as $i => $c)
      <div class="gc-fila-lista">
        <div style="display:flex; align-items:center;">
          <div class="icono" style="background:var(--gc-primario-suave); color:var(--gc-primario-oscuro); font-weight:700;">{{ $i + 1 }}</div>
          <span>{{ \Illuminate\Support\Str::limit($c->nombre, 24) }}</span>
        </div>
        <strong>Bs {{ formatoMoneda($c->total) }}</strong>
      </div>
    @empty
      <div class="gc-vacio"><i class="bi bi-people"></i>Aún no hay ventas registradas.</div>
    @endforelse
  </div>
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-box-seam-fill"></i> Productos Más Vendidos</div>
    @forelse($topProductos as $i => $p)
      <div class="gc-fila-lista">
        <div style="display:flex; align-items:center;">
          <div class="icono" style="background:var(--gc-info-suave); color:var(--gc-info); font-weight:700;">{{ $i + 1 }}</div>
          <span class="codigo-chip">{{ $p->codigo }}</span>
        </div>
        <strong>{{ rtrim(rtrim(number_format((float) $p->cantidad, 2, '.', ''), '0'), '.') }} vendidos</strong>
      </div>
    @empty
      <div class="gc-vacio"><i class="bi bi-bar-chart"></i>Aún no hay ventas registradas.</div>
    @endforelse
  </div>
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-exclamation-triangle-fill" style="color:var(--gc-rojo);"></i> Stock Crítico</div>
    @forelse($stockCritico as $p)
      <div class="gc-fila-lista">
        <div style="display:flex; align-items:center;">
          <div class="icono" style="background:var(--gc-rojo-suave); color:var(--gc-rojo);"><i class="bi bi-box-seam"></i></div>
          <div><div style="font-weight:600;">{{ $p->codigo }}</div><div style="color:var(--gc-gris-claro); font-size:11.5px;">{{ \Illuminate\Support\Str::limit($p->descripcion, 26) }}</div></div>
        </div>
        <span class="estado estado-rechazada">{{ $p->stock }}/{{ $p->stock_min }}</span>
      </div>
    @empty
      <div class="gc-vacio"><i class="bi bi-check-circle"></i>Sin alertas de stock.</div>
    @endforelse
  </div>
</div>

<!-- ===== Resumen financiero y fiscal ===== -->
<div class="gc-seccion-titulo"><span><i class="bi bi-clipboard-data-fill" style="color:var(--gc-primario);"></i> Resumen Financiero y Fiscal</span></div>
<div style="display:grid; grid-template-columns:1.3fr 1fr; gap:18px; margin-bottom:18px;">
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-journal-text"></i> Estado de Resultados del Mes (resumen)</div>
    <table class="tabla-giseca"><tbody>
      <tr><td>Ingresos por ventas</td><td class="text-end fw-bold" style="color:var(--gc-verde);">Bs {{ formatoMoneda($totalVentasMes) }}</td></tr>
      <tr><td>Costo de mercadería comprada</td><td class="text-end" style="color:var(--gc-rojo);">Bs {{ formatoMoneda($totalComprasMes) }}</td></tr>
      <tr style="border-top:2px solid var(--gc-texto);"><td class="fw-bold">Utilidad bruta estimada</td><td class="text-end fw-bold">Bs {{ formatoMoneda($utilidadMes) }}</td></tr>
      <tr><td>Margen sobre ventas</td><td class="text-end">{{ number_format($margen, 1) }}%</td></tr>
      <tr><td>Valor actual del inventario</td><td class="text-end">Bs {{ formatoMoneda($valorInventario) }}</td></tr>
    </tbody></table>
  </div>
  <div class="card-giseca">
    <div class="gc-panel-titulo"><i class="bi bi-bank"></i> Posición Fiscal (SIAT)</div>
    <div class="gc-fila-lista"><span>Crédito fiscal (compras)</span><strong style="color:var(--gc-verde);">Bs {{ formatoMoneda($creditoFiscal) }}</strong></div>
    <div class="gc-fila-lista"><span>Débito fiscal (ventas)</span><strong style="color:var(--gc-primario-oscuro);">Bs {{ formatoMoneda($debitoFiscal) }}</strong></div>
    <div class="gc-fila-lista" style="border-top:2px solid var(--gc-texto); padding-top:10px;">
      <span class="fw-bold">Saldo {{ $saldoFiscal >= 0 ? 'por pagar' : 'a favor' }}</span>
      <strong style="color:{{ $saldoFiscal >= 0 ? 'var(--gc-rojo)' : 'var(--gc-verde)' }};">Bs {{ formatoMoneda(abs($saldoFiscal)) }}</strong>
    </div>
    <a href="{{ route('tributario.index') }}" class="btn-giseca btn-outline btn-sm no-imprimir" style="width:100%; justify-content:center; margin-top:12px;">Ver detalle completo →</a>
  </div>
</div>

<div class="card-giseca no-imprimir" style="display:flex; align-items:center; justify-content:center; gap:8px; color:var(--gc-gris-claro); font-size:12px; padding:12px;">
  <i class="bi bi-database-fill"></i> Datos en tiempo real desde la base de datos MySQL del sistema.
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
try {
  new Chart(document.getElementById('chartVentasCompras'), {
    type: 'bar',
    data: { labels: @json($etiquetas), datasets: [
      { label: 'Ventas', data: @json($datosVentas), backgroundColor: '#E8622C', borderRadius: 4 },
      { label: 'Compras', data: @json($datosCompras), backgroundColor: '#1F2A44', borderRadius: 4 },
    ]},
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }, scales: { y: { beginAtZero: true } } },
  });

  new Chart(document.getElementById('chartFlujoCaja'), {
    type: 'line',
    data: { labels: @json($etiquetas), datasets: [
      { label: 'Ingresos', data: @json($datosIngresos), borderColor: '#2E7D53', backgroundColor: 'rgba(46,125,83,.08)', fill: true, tension: .3 },
      { label: 'Egresos', data: @json($datosEgresos), borderColor: '#C4392B', backgroundColor: 'rgba(196,57,43,.08)', fill: true, tension: .3 },
    ]},
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }, scales: { y: { beginAtZero: true } } },
  });

  const datosUtilidad = @json($datosUtilidad);
  new Chart(document.getElementById('chartUtilidad'), {
    type: 'bar',
    data: { labels: @json($etiquetas), datasets: [{ label: 'Utilidad (Bs)', data: datosUtilidad, backgroundColor: datosUtilidad.map(v => v >= 0 ? '#2E7D53' : '#C4392B'), borderRadius: 4 }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
  });

  const marcas = @json($ventasPorMarca->pluck('marca'));
  const totalesMarca = @json($ventasPorMarca->pluck('total')->map(fn($v) => (float) $v));
  new Chart(document.getElementById('chartMarca'), {
    type: 'doughnut',
    data: { labels: marcas.length ? marcas : ['Sin ventas todavía'],
      datasets: [{ data: marcas.length ? totalesMarca : [1], backgroundColor: ['#1F2A44','#E8622C','#5B6472','#B8860B','#2E7D53','#1E5FA8'] }] },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } },
  });
} catch (e) {
  ['chartVentasCompras','chartFlujoCaja','chartUtilidad','chartMarca'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.parentElement.innerHTML = '<div class="gc-vacio"><i class="bi bi-wifi-off"></i>Gráfico no disponible sin conexión a internet.</div>';
  });
}
</script>
@endpush
