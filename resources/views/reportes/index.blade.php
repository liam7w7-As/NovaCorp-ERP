@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<h6 style="border-bottom:2px solid var(--gc-primario); display:inline-block; padding-bottom:6px; margin-bottom:16px; font-weight:700;">
  Reporte de Proformas
</h6>

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px;">
  <div class="kpi azul"><div class="label">Emitidas</div><div class="value">{{ $emitidas }}</div></div>
  <div class="kpi verde"><div class="label">Convertidas</div><div class="value">{{ $convertidas }}</div></div>
  <div class="kpi naranja"><div class="label">Tasa conversión</div><div class="value">{{ $tasa }}%</div></div>
  <div class="kpi gris"><div class="label">Valor cotizado</div><div class="value">Bs {{ formatoMoneda($valorTotal) }}</div></div>
</div>

<div class="card-giseca" style="margin-bottom:26px;">
  <div class="gc-panel-titulo" style="font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; margin-bottom:14px;"><i class="bi bi-file-earmark-text" style="color:var(--gc-primario);"></i> Detalle de Proformas</div>
  <table class="tabla-giseca">
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

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">

    <h6 style="border-bottom:2px solid var(--gc-primario); display:inline-block; padding-bottom:6px; margin:0; font-weight:700;">
        Resumen de Ventas (últimos 6 meses)
    </h6>


    <form method="GET" action="{{ route('reportes.index') }}">

        <select 
            name="factura"
            class="form-control-giseca"
            onchange="this.form.submit()"
            style="width:200px;">

            <option value="todos" 
                {{ ($facturaFiltro ?? 'todos') == 'todos' ? 'selected' : '' }}>
                Todas las ventas
            </option>


            <option value="con"
                {{ ($facturaFiltro ?? '') == 'con' ? 'selected' : '' }}>
                Con factura
            </option>


            <option value="sin"
                {{ ($facturaFiltro ?? '') == 'sin' ? 'selected' : '' }}>
                Sin factura
            </option>

        </select>

    </form>

</div>

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px;">
  <div class="kpi naranja"><div class="label"><i class="bi bi-cash-coin"></i> Total vendido</div><div class="value">Bs {{ formatoMoneda($kpiVentas['total']) }}</div></div>
  <div class="kpi verde"><div class="label"><i class="bi bi-wallet2"></i> Contado</div><div class="value">Bs {{ formatoMoneda($kpiVentas['contado']) }}</div></div>
  <div class="kpi azul"><div class="label"><i class="bi bi-hourglass-split"></i> Crédito</div><div class="value">Bs {{ formatoMoneda($kpiVentas['credito']) }}</div></div>
  <div class="kpi gris"><div class="label"><i class="bi bi-receipt"></i> Documentos</div><div class="value">{{ $kpiVentas['documentos'] }}</div></div>
</div>

<div class="card-giseca">
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
