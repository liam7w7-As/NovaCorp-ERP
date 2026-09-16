@extends('layouts.app')

@section('title', 'Papelera')

@section('content')
<p style="font-size:12.5px; color:var(--gc-gris);">Los registros eliminados quedan aquí. Restaurar no mueve stock: si era un documento, revisa el inventario antes de seguir operando.</p>

@forelse($grupos as $clave => $items)
  <div class="card-giseca" style="margin-bottom:18px; padding:0; overflow:hidden;">
    <div style="padding:12px 18px; font-weight:700;">{{ $etiquetas[$clave] }} ({{ $items->count() }})</div>
    <table class="tabla-giseca">
      <tbody>
        @foreach($items as $it)
          <tr>
            <td>
              @php
                $titulo = $it->numero ?? $it->nombre ?? $it->codigo ?? $it->concepto ?? ('#'.$it->id);
              @endphp
              <strong>{{ $titulo }}</strong>
              <span style="color:var(--gc-gris-claro); font-size:11.5px;"> · eliminado {{ $it->deleted_at->format('Y-m-d H:i') }}</span>
            </td>
            <td class="text-end" style="white-space:nowrap;">
              <form method="POST" action="{{ route('papelera.restaurar', [$clave, $it->id]) }}" style="display:inline;">@csrf<button class="btn-giseca btn-outline btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button></form>
              <form method="POST" action="{{ route('papelera.eliminar', [$clave, $it->id]) }}" style="display:inline;" onsubmit="return confirm('Eliminar DEFINITIVAMENTE. No se puede deshacer.')">@csrf @method('DELETE')<button class="btn-giseca btn-outline btn-sm"><i class="bi bi-trash" style="color:var(--gc-rojo);"></i> Definitivo</button></form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@empty
  <div class="card-giseca" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-trash" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Papelera vacía.</div>
@endforelse
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
