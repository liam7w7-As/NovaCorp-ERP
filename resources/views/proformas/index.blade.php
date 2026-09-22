@extends('layouts.app')

@section('title', 'Proformas')

@section('content')
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('proformas.index') }}" style="margin:0; display:flex; gap:8px; flex-wrap:wrap;">
    <select name="estado" class="form-control-giseca" style="width:200px;" onchange="this.form.submit()">
      <option value="">Todos los estados</option>
      @foreach(['borrador' => 'BORRADOR', 'enviada' => 'ENVIADA', 'aprobada' => 'APROBADA', 'rechazada' => 'RECHAZADA', 'vencida' => 'VENCIDA', 'convertida' => 'CONVERTIDA'] as $val => $lbl)
        <option value="{{ $val }}" {{ $estado === $val ? 'selected' : '' }}>{{ $lbl }}</option>
      @endforeach
    </select>
    <input type="text" name="q" class="form-control-giseca" style="width:220px;" placeholder="N°, cliente..." value="{{ $q }}">
    <input type="date" name="desde" class="form-control-giseca" style="width:150px;" value="{{ $desde }}" title="Desde">
    <input type="date" name="hasta" class="form-control-giseca" style="width:150px;" value="{{ $hasta }}" title="Hasta">
  </form>
  <a href="{{ route('proformas.create') }}" class="btn-giseca btn-primario"><i class="bi bi-plus-lg"></i> Nueva Proforma</a>
</div>

<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; font-size:12px;">
  @foreach($resumen as $e => $n)
    <a href="{{ route('proformas.index', ['estado' => $e]) }}" class="estado estado-{{ $e }}" style="{{ $estado === $e ? 'outline:2px solid var(--gc-primario);' : '' }}">{{ strtoupper($e) }}: {{ $n }}</a>
  @endforeach
</div>

@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      @forelse($proformas as $p)
        <tr>
          <td><span class="codigo-chip">{{ $p->numero }}</span></td>
          <td>{{ $p->fecha->format('Y-m-d') }}</td>
          <td>{{ $p->cliente_nombre }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($p->total) }}</td>
          <td><span class="estado estado-{{ $p->estado }}">{{ strtoupper($p->estado) }}</span></td>
          <td class="text-end" style="white-space:nowrap;">
            <a href="{{ route('proformas.show', $p) }}" class="btn-giseca btn-outline btn-icon btn-sm" title="Ver"><i class="bi bi-eye"></i></a>
            @if(!$p->esta_convertida)
              <a href="{{ route('proformas.edit', $p) }}" class="btn-giseca btn-outline btn-icon btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
              @can('admin')<form method="POST" action="{{ route('proformas.destroy', $p) }}" style="display:inline;" data-confirm="¿Eliminar la proforma {{ $p->numero }}?" data-confirm-title="Eliminar proforma" data-confirm-label="Eliminar" data-confirm-variant="peligro">@csrf @method('DELETE')<button type="submit" class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i class="bi bi-trash" style="color:var(--gc-rojo);"></i></button></form>@endcan
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-file-earmark-plus" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Aún no hay proformas. <a href="{{ route('proformas.create') }}">Crear la primera →</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($proformas->hasPages())
  <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:12.5px; color:var(--gc-gris);">
    <span>Mostrando {{ $proformas->firstItem() }}–{{ $proformas->lastItem() }} de {{ $proformas->total() }}</span>
    <div style="display:flex; gap:8px;">
      @if($proformas->onFirstPage())<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">← Anterior</span>@else<a class="btn-giseca btn-outline btn-sm" href="{{ $proformas->previousPageUrl() }}">← Anterior</a>@endif
      @if($proformas->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $proformas->nextPageUrl() }}">Siguiente →</a>@else<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">Siguiente →</span>@endif
    </div>
  </div>
@endif
@endsection

@push('scripts')
<script>
function mostrarToast(mensaje, tipo) {
  let t = document.getElementById('toastGiseca');
  if (!t) { t = document.createElement('div'); t.id = 'toastGiseca'; t.className = 'toast-giseca'; document.body.appendChild(t); }
  t.textContent = mensaje; t.className = 'toast-giseca mostrar ' + (tipo || '');
  setTimeout(() => t.classList.remove('mostrar'), 2800);
}
@if(session('exito')) mostrarToast(@json(session('exito')), 'exito'); @endif
@if(session('error')) mostrarToast(@json(session('error')), 'error'); @endif
</script>
@endpush
