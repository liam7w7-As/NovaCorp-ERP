@extends('layouts.app')

@section('title', 'Kardex e Inventario Valorizado')

@section('content')
<div class="card-giseca" style="margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
    <strong>Inventario valorizado (costo)</strong>
    <span class="codigo-chip">Total: Bs {{ formatoMoneda($totalValor) }}</span>
  </div>
  <table class="tabla-giseca">
    <thead><tr><th>Código</th><th>Descripción</th><th class="text-end">Stock</th><th class="text-end">Costo</th><th class="text-end">Valor</th><th></th></tr></thead>
    <tbody>
      @foreach($valorizado as $v)
        <tr class="{{ $v['producto']->en_alerta ? 'alerta' : '' }}">
          <td><span class="codigo-chip">{{ $v['producto']->codigo }}</span></td>
          <td>{{ \Illuminate\Support\Str::limit($v['producto']->descripcion, 40) }}</td>
          <td class="text-end">{{ $v['producto']->stock }}</td>
          <td class="text-end">{{ formatoMoneda($v['producto']->costo) }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($v['valor']) }}</td>
          <td class="text-end"><a class="btn-giseca btn-outline btn-sm" href="{{ route('kardex.show', $v['producto']) }}"><i class="bi bi-list-ul"></i> Kardex</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
