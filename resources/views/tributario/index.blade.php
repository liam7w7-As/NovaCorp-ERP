@extends('layouts.app')

@section('title', 'Módulo Tributario')

@section('content')
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px;">
  <div class="kpi naranja"><div class="label"><i class="bi bi-cart-plus-fill"></i> Crédito fiscal (compras)</div><div class="value">Bs {{ formatoMoneda($creditoFiscal) }}</div></div>
  <div class="kpi verde"><div class="label"><i class="bi bi-cash-coin"></i> Débito fiscal (ventas)</div><div class="value">Bs {{ formatoMoneda($debitoFiscal) }}</div></div>
  <div class="kpi azul"><div class="label"><i class="bi bi-calculator"></i> Saldo {{ $saldo >= 0 ? 'por pagar' : 'a favor' }}</div><div class="value">Bs {{ formatoMoneda(abs($saldo)) }}</div></div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:18px;">
  <div class="card-giseca">
    <h6 style="margin-top:0;"><i class="bi bi-cart-plus-fill" style="color:var(--gc-primario);"></i> Crédito Fiscal — Compras (SIAT)</h6>
    <table class="tabla-giseca">
      <thead><tr><th>Proveedor</th><th>Factura</th><th class="text-end">Base CF</th><th class="text-end">Crédito Fiscal</th></tr></thead>
      <tbody>
        @forelse($comprasSiat as $c)
          <tr>
            <td>{{ $c->proveedor_nombre }}</td>
            <td><span class="codigo-chip">{{ $c->numero_factura_siat ?: $c->numero }}</span></td>
            <td class="text-end">{{ formatoMoneda($c->base_cf) }}</td>
            <td class="text-end fw-bold">{{ formatoMoneda($c->credito_fiscal) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:26px;">
            <i class="bi bi-bank" style="font-size:26px; display:block; margin-bottom:6px; opacity:.5;"></i>
            Aún no importaste el Libro de Compras del SIAT. <a href="{{ route('compras.index') }}">Importarlo →</a>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-giseca">
    <h6 style="margin-top:0;"><i class="bi bi-cash-coin" style="color:var(--gc-verde);"></i> Débito Fiscal — Ventas (SIAT)</h6>
    <table class="tabla-giseca">
      <thead><tr><th>Cliente</th><th>Factura</th><th class="text-end">Base DF</th><th class="text-end">Débito Fiscal</th></tr></thead>
      <tbody>
        @forelse($ventasSiat as $v)
          <tr>
            <td>{{ $v->cliente_nombre }}</td>
            <td><span class="codigo-chip">{{ $v->numero_factura_siat ?: $v->numero }}</span></td>
            <td class="text-end">{{ formatoMoneda($v->base_df) }}</td>
            <td class="text-end fw-bold">{{ formatoMoneda($v->debito_fiscal) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:26px;">
            <i class="bi bi-bank" style="font-size:26px; display:block; margin-bottom:6px; opacity:.5;"></i>
            Aún no importaste el Libro de Ventas del SIAT. <a href="{{ route('ventas.index') }}">Importarlo →</a>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card-giseca no-imprimir" style="margin-top:18px; font-size:12px; color:var(--gc-gris-claro); text-align:center;">
  <i class="bi bi-info-circle"></i> Estos montos se calculan de las facturas importadas desde el Libro de Compras/Ventas del SIAT en los módulos Compras y Ventas.
  No incluyen compras/ventas registradas manualmente sin ese origen (esas no traen datos fiscales oficiales del SIN).
</div>
@endsection
