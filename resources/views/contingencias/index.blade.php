@extends('layouts.app')

@section('title', 'Eventos de Contingencia')

@section('content')
@php $badge = ['abierto' => 'estado-rechazada', 'cerrado' => 'estado-vencida', 'enviado' => 'estado-enviada', 'validado' => 'estado-aprobada']; @endphp

@if($abierto)
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
    <i class="bi bi-exclamation-triangle-fill"></i> Evento abierto: <strong>{{ $abierto->descripcion }}</strong> (desde {{ $abierto->fecha_inicio->format('Y-m-d H:i') }}).
    Las emisiones en contingencia se vincularán a este evento.
  </div>
@else
  <div class="card-giseca" style="margin-bottom:16px;">
    <h6>Abrir evento significativo</h6>
    <form method="POST" action="{{ route('contingencias.abrir') }}" data-confirm="Se abrirá un evento de contingencia y las nuevas emisiones fuera de línea quedarán vinculadas a él." data-confirm-title="Abrir contingencia" data-confirm-label="Registrar inicio">
      @csrf
      <div style="display:grid; grid-template-columns:1fr 2fr auto; gap:10px; align-items:end;">
        <div><label class="form-label-giseca">Código SIN (1-7) *</label>
          <select name="codigo_evento" class="form-control-giseca" required>
            @forelse(\App\Models\CatalogoSin::lista('evento') as $ev)
              <option value="{{ $ev->codigo }}">{{ $ev->codigo }} — {{ $ev->descripcion }}</option>
            @empty
              @foreach(\App\Models\EventoContingencia::CODIGOS as $cod => $desc)
                <option value="{{ $cod }}">{{ $cod }} — {{ $desc }}</option>
              @endforeach
            @endforelse
          </select>
        </div>
        <div><label class="form-label-giseca">Descripción</label><input name="descripcion" class="form-control-giseca" placeholder="Detalle del incidente"></div>
        <div><button class="btn-giseca btn-primario">Registrar inicio</button></div>
      </div>
    </form>
  </div>
@endif

@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>Evento</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Paquete</th><th></th></tr></thead>
    <tbody>
      @forelse($eventos as $ev)
        <tr>
          <td><span class="codigo-chip">EV-{{ $ev->id }}</span> {{ \Illuminate\Support\Str::limit($ev->descripcion, 40) }}<div style="font-size:10.5px; color:var(--gc-gris-claro);">Código SIN {{ $ev->codigo_evento }} · {{ $ev->facturas()->count() }} factura(s)</div></td>
          <td>{{ $ev->fecha_inicio->format('Y-m-d H:i') }}</td>
          <td>{{ $ev->fecha_fin ? $ev->fecha_fin->format('Y-m-d H:i') : '—' }}</td>
          <td><span class="estado {{ $badge[$ev->estado] }}">{{ strtoupper($ev->estado) }}</span></td>
          <td>
            @if($ev->codigo_recepcion_paquete)
              <span class="codigo-chip equivalente">{{ $ev->codigo_recepcion_paquete }}</span>
              <span class="estado {{ $ev->estado_paquete === 'validado' ? 'estado-aprobada' : 'estado-enviada' }}">{{ strtoupper($ev->estado_paquete) }}</span>
            @else — @endif
          </td>
          <td class="text-end" style="white-space:nowrap;">
            @if($ev->estado === 'abierto')
              <form method="POST" action="{{ route('contingencias.cerrar', $ev) }}" style="display:inline;" data-confirm="Se cerrará el evento EV-{{ $ev->id }} y ya no recibirá nuevas facturas." data-confirm-title="Cerrar contingencia" data-confirm-label="Cerrar evento">@csrf<button class="btn-giseca btn-outline btn-sm">Cerrar evento</button></form>
            @endif
            @if(in_array($ev->estado, ['cerrado', 'enviado']))
              <form method="POST" action="{{ route('contingencias.empaquetar', $ev) }}" style="display:inline;" data-confirm="Se generará el paquete de facturas del evento EV-{{ $ev->id }} y se enviará al SIN." data-confirm-title="Enviar paquete al SIN" data-confirm-label="Empaquetar y enviar">@csrf<button class="btn-giseca btn-outline btn-sm"><i class="bi bi-box-seam"></i> Empaquetar y enviar</button></form>
            @endif
            @if($ev->estado === 'enviado')
              <form method="POST" action="{{ route('contingencias.validar', $ev) }}" style="display:inline;">@csrf<button class="btn-giseca btn-outline btn-sm"><i class="bi bi-check-circle"></i> Validar</button></form>
            @endif
            @if($ev->paquete_path)
              <a class="btn-giseca btn-outline btn-sm" href="{{ route('contingencias.descargar', $ev) }}"><i class="bi bi-download"></i> .tar.gz</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-lightning-charge" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin eventos registrados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($eventos->hasPages())
  <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
    @if(!$eventos->onFirstPage())<a class="btn-giseca btn-outline btn-sm" href="{{ $eventos->previousPageUrl() }}">← Anterior</a>@endif
    @if($eventos->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $eventos->nextPageUrl() }}">Siguiente →</a>@endif
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
