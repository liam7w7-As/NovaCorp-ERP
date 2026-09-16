@extends('layouts.app')

@section('title', 'Kardex ' . $producto->codigo)

@section('content')
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('kardex.show', $producto) }}" style="margin:0;" id="formKardex">
    <select class="form-control-giseca" style="width:320px;" onchange="if(this.value) window.location='{{ url('/kardex') }}/'+this.value">
      <option value="">— Cambiar producto —</option>
      @foreach($productos as $p)
        <option value="{{ $p->id }}" {{ $p->id === $producto->id ? 'selected' : '' }}>{{ $p->codigo }} — {{ \Illuminate\Support\Str::limit($p->descripcion, 35) }}</option>
      @endforeach
    </select>
  </form>
  <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <form method="GET" action="{{ route('kardex.show', $producto) }}" style="display:flex; gap:6px; margin:0; align-items:center;">
      <input type="date" name="desde" value="{{ $desde ?? '' }}" class="form-control-giseca" style="width:auto;">
      <input type="date" name="hasta" value="{{ $hasta ?? '' }}" class="form-control-giseca" style="width:auto;">
      <button class="btn-giseca btn-outline btn-sm">Filtrar</button>
      @if(! empty($desde) || ! empty($hasta))
        <a class="btn-giseca btn-outline btn-sm" href="{{ route('kardex.show', $producto) }}">Limpiar</a>
      @endif
    </form>
    <span class="codigo-chip">{{ $producto->codigo }}</span>
    <span style="font-size:13px;">Stock actual: <strong>{{ $producto->stock }}</strong> · Valorizado: <strong>Bs {{ formatoMoneda((float) $producto->stock * (float) $producto->costo) }}</strong></span>
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('kardex.index') }}">← Volver</a>
  </div>
</div>

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>Fecha</th><th>Documento</th><th>Movimiento</th><th>Detalle</th><th class="text-end">Entrada</th><th class="text-end">Salida</th><th class="text-end">Saldo</th></tr></thead>
    <tbody>
      @if(! empty($desde))
        <tr style="background:var(--gc-primario-suave, #f2f2f2);">
          <td colspan="6" class="text-end fw-bold">Saldo inicial al {{ $desde }}</td>
          <td class="text-end fw-bold">{{ $saldoInicial }}</td>
        </tr>
      @endif
      @forelse($movimientos as $m)
        <tr>
          <td>{{ $m['fecha'] }}</td>
          <td><span class="codigo-chip">{{ $m['documento'] }}</span></td>
          <td><span class="estado {{ $m['tipo'] === 'COMPRA' ? 'estado-aprobada' : 'estado-enviada' }}">{{ $m['tipo'] }}</span></td>
          <td>{{ \Illuminate\Support\Str::limit($m['detalle'], 30) }}</td>
          <td class="text-end" style="color:var(--gc-verde);">{{ $m['entrada'] ?: '—' }}</td>
          <td class="text-end" style="color:var(--gc-rojo);">{{ $m['salida'] ?: '—' }}</td>
          <td class="text-end fw-bold">{{ $m['saldo'] }}</td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center; color:var(--gc-gris-claro); padding:30px;">Sin movimientos para este producto.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p style="font-size:11.5px; color:var(--gc-gris-claro);">Solo ventas activas. Anulaciones y eliminaciones ya revirtieron su efecto.</p>
@endsection
