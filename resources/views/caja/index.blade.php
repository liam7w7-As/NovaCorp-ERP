@extends('layouts.app')

@section('title', 'Caja Diaria')

@section('content')
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px;">
  <div class="kpi verde"><div class="label"><i class="bi bi-arrow-down-circle"></i> Ingresos del día</div><div class="value">Bs {{ formatoMoneda($ingresos) }}</div></div>
  <div class="kpi naranja"><div class="label"><i class="bi bi-arrow-up-circle"></i> Egresos del día</div><div class="value">Bs {{ formatoMoneda($egresos) }}</div></div>
  <div class="kpi azul"><div class="label"><i class="bi bi-wallet2"></i> Balance</div><div class="value">Bs {{ formatoMoneda($balance) }}</div></div>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('caja.index') }}" style="margin:0; display:flex; gap:8px; align-items:center;">
    <label class="form-label-giseca" style="margin:0;">Fecha:</label>
    <input type="date" name="fecha" class="form-control-giseca" style="width:170px;" value="{{ $fecha }}" onchange="this.form.submit()">
  </form>
  <a class="btn-giseca btn-outline btn-sm" href="{{ route('cuentas.index') }}"><i class="bi bi-list-check"></i> Ver cuentas por cobrar/pagar</a>
</div>

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>N°</th><th>Tipo</th><th>Concepto</th><th>Entidad</th><th class="text-end">Monto</th><th>Pago</th></tr></thead>
    <tbody>
      @forelse($movimientos as $m)
        <tr>
          <td><span class="codigo-chip {{ $m->tipo === 'ingreso' ? 'equivalente' : '' }}">{{ $m->numero }}</span></td>
          <td><span class="estado {{ $m->tipo === 'ingreso' ? 'estado-aprobada' : 'estado-rechazada' }}">{{ strtoupper($m->tipo) }}</span></td>
          <td>{{ \Illuminate\Support\Str::limit($m->concepto, 45) }}</td>
          <td>{{ $m->entidad ?: '—' }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($m->monto) }}</td>
          <td style="font-size:11.5px; color:var(--gc-gris-claro);">{{ $m->pagos->first()->forma_pago ?? $m->metodo }}</td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-wallet2" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin movimientos este día.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($movimientos->hasPages())
  <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
    @if(!$movimientos->onFirstPage())<a class="btn-giseca btn-outline btn-sm" href="{{ $movimientos->previousPageUrl() }}">← Anterior</a>@endif
    @if($movimientos->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $movimientos->nextPageUrl() }}">Siguiente →</a>@endif
  </div>
@endif
@endsection
