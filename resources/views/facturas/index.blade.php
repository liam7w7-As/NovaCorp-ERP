@extends('layouts.app')

@section('title', 'Facturación Electrónica')

@section('content')
@php
  $badge = ['emitida' => 'estado-aprobada', 'anulada' => 'estado-rechazada', 'rechazada' => 'estado-rechazada', 'pendiente' => 'estado-borrador', 'observada' => 'estado-vencida'];
@endphp

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('facturas.index') }}" style="margin:0; display:flex; gap:8px; flex-wrap:wrap;">
    <select name="estado" class="form-control-giseca" style="width:160px;" onchange="this.form.submit()">
      <option value="">Todos los estados</option>
      @foreach(['emitida' => 'EMITIDA', 'pendiente' => 'PENDIENTE', 'observada' => 'OBSERVADA', 'rechazada' => 'RECHAZADA', 'anulada' => 'ANULADA'] as $val => $lbl)
        <option value="{{ $val }}" {{ $estado === $val ? 'selected' : '' }}>{{ $lbl }}</option>
      @endforeach
    </select>
    <select name="sucursal_id" class="form-control-giseca" style="width:170px;" onchange="this.form.submit()">
      <option value="">Todas las sucursales</option>
      @foreach($sucursales as $s)
        <option value="{{ $s->id }}" {{ (string)$sucursal_id === (string)$s->id ? 'selected' : '' }}>{{ $s->nombre }} (Suc. {{ $s->codigo }})</option>
      @endforeach
    </select>
    <input type="text" name="q" class="form-control-giseca" style="width:200px;" placeholder="N°, CUF, recepción..." value="{{ $q }}">
    <input type="date" name="desde" class="form-control-giseca" style="width:140px;" value="{{ $desde }}">
    <input type="date" name="hasta" class="form-control-giseca" style="width:140px;" value="{{ $hasta }}">
  </form>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <a class="btn-giseca btn-primario" href="{{ route('ventas.index', ['tipo' => 'con_factura']) }}"><i class="bi bi-plus-lg"></i> Facturar venta</a>
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.reporte', ['sucursal_id' => $sucursal_id]) }}"><i class="bi bi-download"></i> Anulaciones CSV</a>
  </div>
</div>

<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; font-size:12px;">
  @foreach($resumen as $e => $n)
    <a href="{{ route('facturas.index', ['estado' => $e, 'sucursal_id' => $sucursal_id]) }}" class="estado {{ $badge[$e] ?? 'estado-borrador' }}" style="{{ $estado === $e ? 'outline:2px solid var(--gc-primario);' : '' }}">{{ strtoupper($e) }}: {{ $n }}</a>
  @endforeach
</div>

@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>N° Factura</th><th>Sucursal / POS</th><th>CUF</th><th>Cliente</th><th>Fecha</th><th class="text-end">Total</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      @forelse($facturas as $f)
        <tr>
          <td><span class="codigo-chip">{{ $f->numero_factura }}</span>@if($f->simulada)<div style="font-size:10px; color:var(--gc-gris-claro);">SIMULADA</div>@endif</td>
          <td>
            <div style="font-weight:600; font-size:12px;">{{ $f->sucursal?->nombre ?? 'Casa Matriz' }}</div>
            <div style="font-size:10px; color:var(--gc-gris-claro);">Suc. {{ $f->codigo_sucursal }} · POS {{ $f->codigo_punto_venta }}</div>
          </td>
          <td style="max-width:160px; overflow:hidden; text-overflow:ellipsis;" title="{{ $f->cuf }}"><span style="font-size:11.5px;">{{ \Illuminate\Support\Str::limit($f->cuf, 18) }}</span></td>
          <td>{{ $f->venta->cliente_nombre ?? '—' }}</td>
          <td>{{ $f->fecha_emision->format('Y-m-d H:i') }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($f->venta->total ?? 0) }}</td>
          <td><span class="estado {{ $badge[$f->estado] ?? 'estado-borrador' }}">{{ strtoupper($f->estado) }}</span></td>
          <td class="text-end" style="white-space:nowrap;">
            <a class="btn-giseca btn-outline btn-icon btn-sm" title="Ver detalle" href="{{ route('facturas.show', $f) }}"><i class="bi bi-eye"></i></a>
            <a class="btn-giseca btn-outline btn-icon btn-sm" title="PDF Carta" href="{{ route('facturas.pdf', $f) }}"><i class="bi bi-file-earmark-pdf"></i></a>
            <a class="btn-giseca btn-outline btn-icon btn-sm" title="PDF Medio Oficio" href="{{ route('facturas.pdf-medio-oficio', $f) }}"><i class="bi bi-file-text"></i></a>
            <a class="btn-giseca btn-outline btn-icon btn-sm" title="Ticket 80mm" href="{{ route('facturas.pdf-rollo', $f) }}"><i class="bi bi-receipt"></i></a>
            <a class="btn-giseca btn-outline btn-icon btn-sm" title="Ticket 58mm" href="{{ route('facturas.pdf-rollo-58', $f) }}"><i class="bi bi-receipt-cutoff"></i></a>
            @if($f->xml_firmado)<a class="btn-giseca btn-outline btn-icon btn-sm" title="XML firmado" href="{{ route('facturas.xml', $f) }}"><i class="bi bi-file-earmark-code"></i></a>@endif
            @if($f->estado === 'emitida')
              @can('facturas.emitir')<button class="btn-giseca btn-outline btn-icon btn-sm" title="Anular" onclick="abrirModalAnular('{{ route('facturas.anular', $f) }}', '{{ $f->numero_factura }}')"><i class="bi bi-x-circle" style="color:var(--gc-rojo);"></i></button>@endcan
            @elseif($f->estado !== 'emitida' && $f->estado !== 'anulada')
              @can('admin')<form method="POST" action="{{ route('facturas.destroy', $f) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro?')">@csrf @method('DELETE')<button class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i class="bi bi-trash" style="color:var(--gc-rojo);"></i></button></form>@endcan
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-file-earmark-check" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin facturas electrónicas. Emite una desde una venta con factura.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($facturas->hasPages())
  <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:12.5px; color:var(--gc-gris);">
    <span>Mostrando {{ $facturas->firstItem() }}–{{ $facturas->lastItem() }} de {{ $facturas->total() }}</span>
    <div style="display:flex; gap:8px;">
      @if($facturas->onFirstPage())<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">← Anterior</span>@else<a class="btn-giseca btn-outline btn-sm" href="{{ $facturas->previousPageUrl() }}">← Anterior</a>@endif
      @if($facturas->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $facturas->nextPageUrl() }}">Siguiente →</a>@else<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">Siguiente →</span>@endif
    </div>
  </div>
@endif

<div class="modal-giseca" id="modalAnular">
  <div class="modal-box">
    <h6>Anular Factura ante el SIN</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);" id="anularTexto"></p>
    <form method="POST" action="" id="formAnular">
      @csrf
      <div><label class="form-label-giseca">Motivo de anulación *</label>
        <select name="motivo" class="form-control-giseca" required>
          @forelse(\App\Models\CatalogoSin::lista('motivo') as $m)
            <option value="{{ $m->codigo }}">{{ $m->codigo }} — {{ $m->descripcion }}</option>
          @empty
            <option value="1">1 — Factura mal emitida</option>
            <option value="2">2 — Datos de emisión incorrectos</option>
            <option value="3">3 — Devolución total o parcial</option>
            <option value="4">4 — Desistimiento de la operación</option>
          @endforelse
        </select>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Confirmar anulación</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalAnular').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function abrirModalAnular(url, numero) {
  document.getElementById('formAnular').action = url;
  document.getElementById('anularTexto').textContent = 'Se anulará la factura ' + numero + ' ante el SIN.';
  document.getElementById('modalAnular').classList.add('abierto');
}
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
