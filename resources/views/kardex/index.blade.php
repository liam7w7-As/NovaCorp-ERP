@extends('layouts.app')

@section('title', 'Kardex e Inventario Valorizado')

@section('content')
<div class="card-giseca" style="margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
    <strong>Inventario valorizado (costo)</strong>
    <form method="GET" action="{{ route('kardex.index') }}" style="display:flex; gap:8px; margin:0;">
      <input name="q" value="{{ $q ?? '' }}" class="form-control-giseca" placeholder="Buscar código o descripción" style="width:260px;">
      <button class="btn-giseca btn-outline btn-sm">Buscar</button>
    </form>
    <span class="codigo-chip">Total: Bs {{ formatoMoneda($totalValor) }}</span>
  </div>
  <table class="tabla-giseca">
    <thead><tr><th>Código</th><th>Descripción</th><th class="text-end">Stock</th><th class="text-end">Costo</th><th class="text-end">Valor</th><th></th></tr></thead>
    <tbody>
      @foreach($valorizado as $v)
        <tr class="{{ $v['producto']->en_alerta ? 'alerta' : '' }}">
          <td><span class="codigo-chip">{{ $v['producto']->codigo }}</span></td>
          <td>{{ \Illuminate\Support\Str::limit($v['producto']->descripcion, 40) }}</td>
          <td class="text-end">
            {{ $v['producto']->stock }}
            @if((float) $v['producto']->stock_reservado > 0)
              <div style="font-size:10.5px;color:var(--gc-gris);">Disp. {{ formatoMoneda($v['producto']->stock_disponible) }}</div>
            @endif
          </td>
          <td class="text-end">{{ formatoMoneda($v['producto']->costo) }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($v['valor']) }}</td>
          <td class="text-end"><a class="btn-giseca btn-outline btn-sm" href="{{ route('kardex.show', $v['producto']) }}"><i class="bi bi-list-ul"></i> Kardex</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:12px;">{{ $productos->links() }}</div>
</div>
@endsection
