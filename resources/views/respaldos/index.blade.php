@extends('layouts.app')

@section('title', 'Respaldos')

@section('content')
<div class="card-giseca" style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
  <div style="font-size:13px; color:var(--gc-gris);">Respaldo diario automático a las 02:00 (scheduler) + manual. Se conservan los 10 más recientes. Para restaurar: <span class="codigo-chip">mysql giseca_erp &lt; respaldo.sql</span></div>
  <form method="POST" action="{{ route('respaldos.crear') }}" style="margin:0;">@csrf<button class="btn-giseca btn-primario"><i class="bi bi-hdd"></i> Crear respaldo ahora</button></form>
</div>

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>Archivo</th><th>Fecha</th><th class="text-end">Tamaño</th><th></th></tr></thead>
    <tbody>
      @forelse($archivos as $a)
        <tr>
          <td><span class="codigo-chip">{{ $a['nombre'] }}</span></td>
          <td>{{ $a['fecha'] }}</td>
          <td class="text-end">{{ $a['kb'] }} KB</td>
          <td class="text-end"><a class="btn-giseca btn-outline btn-sm" href="{{ route('respaldos.descargar', $a['nombre']) }}"><i class="bi bi-download"></i> Descargar</a></td>
        </tr>
      @empty
        <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-hdd" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin respaldos todavía.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
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
