@extends('layouts.app')

@section('title', 'Factura ' . $factura->numero_factura)

@push('styles')
<style>
  .factura-doc{ background:#fff; border:1px solid var(--gc-borde); border-radius:10px; padding:26px; font-size:12.5px; max-width:640px; margin:0 auto; }
  .factura-doc .pdf-header{ display:flex; justify-content:space-between; border-bottom:2px solid var(--gc-texto); padding-bottom:12px; margin-bottom:14px; }
  .factura-doc .caja-monto{ background:var(--gc-fondo); border:1px solid var(--gc-borde); border-radius:4px; padding:10px 14px; margin:14px 0; }
  .dato-fiscal{ display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; padding:7px 0; border-bottom:1px solid var(--gc-borde); font-size:12px; }
  .dato-fiscal .et{ font-size:10px; font-weight:700; color:var(--gc-gris); text-transform:uppercase; }
  .dato-fiscal .vl{ word-break:break-all; }
  @media print{ .factura-doc{ border:none; border-radius:0; padding:0; max-width:100%; } }
</style>
@endpush

@section('content')
@php $badge = ['emitida' => 'estado-aprobada', 'anulada' => 'estado-rechazada', 'rechazada' => 'estado-rechazada', 'pendiente' => 'estado-borrador', 'observada' => 'estado-vencida']; @endphp

<div class="no-imprimir" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px; max-width:680px; margin-left:auto; margin-right:auto;">
  <span class="estado {{ $badge[$factura->estado] }}" style="font-size:12.5px; padding:5px 14px;">{{ strtoupper($factura->estado) }}{{ $factura->simulada ? ' · SIMULADA' : '' }}</span>
  <div style="display:flex; gap:6px; flex-wrap:wrap;">
    <button class="btn-giseca btn-oscuro btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.pdf', $factura) }}" title="Formato Carta Oficial"><i class="bi bi-file-earmark-pdf"></i> Carta</a>
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.pdf-medio-oficio', $factura) }}" title="Formato Medio Oficio"><i class="bi bi-file-text"></i> Medio Oficio</a>
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.pdf-rollo', $factura) }}" title="Ticket térmico 80mm"><i class="bi bi-receipt"></i> Rollo 80</a>
    <a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.pdf-rollo-58', $factura) }}" title="Ticket térmico 58mm"><i class="bi bi-receipt-cutoff"></i> Rollo 58</a>
    @if($factura->xml_firmado)<a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.xml', $factura) }}"><i class="bi bi-file-earmark-code"></i> XML</a>@endif
    <button class="btn-giseca btn-outline btn-sm" onclick="document.getElementById('modalCorreo').classList.add('abierto')" title="Enviar o reenviar factura por correo"><i class="bi bi-envelope"></i> Correo</button>
    @if($factura->estado === 'emitida')
      @can('facturas.emitir')<button class="btn-giseca btn-outline btn-sm" onclick="document.getElementById('modalAnular').classList.add('abierto')"><i class="bi bi-x-circle" style="color:var(--gc-rojo);"></i> Anular</button>@endcan
    @endif
    @if($factura->estado === 'anulada')
      @can('facturas.emitir')<form method="POST" action="{{ route('facturas.revertir', $factura) }}" style="display:inline;" onsubmit="return confirm('¿Revertir la anulación? La factura vuelve a EMITIDA conforme a normativa SIN.')">@csrf<button class="btn-giseca btn-outline btn-sm"><i class="bi bi-arrow-counterclockwise" style="color:var(--gc-verde);"></i> Revertir anulación</button></form>@endcan
    @endif
  </div>
</div>

@if(session('exito'))
  <div class="no-imprimir" style="background:var(--gc-verde-suave); color:var(--gc-verde); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px; max-width:680px; margin-left:auto; margin-right:auto;">{{ session('exito') }}</div>
@endif
@if(session('error'))
  <div class="no-imprimir" style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px; max-width:680px; margin-left:auto; margin-right:auto;">{{ session('error') }}</div>
@endif

<div class="factura-doc" style="max-width:680px;">
  <div class="pdf-header">
    <div>
      <div style="font-weight:800; font-size:18px;">GISECA SRL</div>
      <div>NIT: {{ \App\Services\SiatConfig::get('siat_nit', '—') }}</div>
      @if($factura->sucursal)
        <div style="font-weight:600; color:var(--gc-primario-oscuro); margin-top:2px;">{{ $factura->sucursal->nombre }} (Sucursal {{ $factura->codigo_sucursal }})</div>
        <div>{{ $factura->sucursal->direccion }}</div>
        <div>{{ $factura->sucursal->municipio ?? 'Santa Cruz' }} - Bolivia</div>
      @else
        <div>Casa Matriz (Sucursal 0)</div>
        <div>{{ \App\Services\SiatConfig::get('siat_direccion', '') }}</div>
      @endif
      @if($factura->puntoVenta)
        <div style="font-size:11px; color:var(--gc-gris); margin-top:2px;">Punto de Venta: {{ $factura->puntoVenta->nombre }} (Cod: {{ $factura->codigo_punto_venta }})</div>
      @endif
    </div>
    <div style="text-align:right;">
      <div style="font-weight:800; font-size:16px;">FACTURA <span class="codigo-chip">{{ $factura->numero_factura }}</span></div>
      <div>Emisión: {{ $factura->fecha_emision->format('Y-m-d H:i') }}</div>
      <div>Venta: {{ $factura->venta->numero ?? '—' }}</div>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px;">
    <div><strong>Señor(es):</strong> {{ $factura->venta->cliente_nombre ?? '—' }}</div>
    <div><strong>NIT/CI:</strong> {{ $factura->venta->nit_cliente ?? '—' }}</div>
  </div>

  <table class="tabla-giseca" style="margin-bottom:14px;">
    <thead><tr><th>Cant.</th><th>Descripción</th><th class="text-end">P.Unit.</th><th class="text-end">Total</th></tr></thead>
    <tbody>
      @foreach($factura->venta->detalles ?? [] as $it)
        <tr><td>{{ $it->cantidad }}</td><td>{{ $it->descripcion_producto }} <span style="color:var(--gc-gris-claro);">({{ $it->codigo_producto }})</span></td>
            <td class="text-end">{{ formatoMoneda($it->precio_unitario) }}</td><td class="text-end">{{ formatoMoneda($it->subtotal) }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <div style="display:flex; justify-content:flex-end; margin-bottom:14px;">
    <div style="width:260px;">
      <div style="display:flex; justify-content:space-between; padding:3px 0;"><span>Subtotal:</span><span>{{ formatoMoneda($factura->venta->subtotal ?? 0) }}</span></div>
      <div style="display:flex; justify-content:space-between; padding:3px 0;"><span>Descuento:</span><span>{{ formatoMoneda($factura->venta->descuento ?? 0) }}</span></div>
      <div style="display:flex; justify-content:space-between; padding:6px 0; border-top:2px solid var(--gc-texto); font-weight:800; font-size:16px;">
        <span>TOTAL:</span><span style="color:var(--gc-primario-oscuro);">Bs {{ formatoMoneda($factura->venta->total ?? 0) }}</span>
      </div>
    </div>
  </div>

  <div style="font-size:11.5px; background:var(--gc-fondo); border:1px solid var(--gc-borde); border-radius:6px; padding:10px 14px; margin-bottom:14px;">{{ $factura->leyenda }}</div>

  <h6 style="margin:14px 0 6px;">Datos fiscales SIN</h6>
  <div class="dato-fiscal"><div><div class="et">CUF</div><div class="vl">{{ $factura->cuf }}</div></div><div><div class="et">CUFD</div><div class="vl">{{ $factura->cufd ?: '—' }}</div></div></div>
  <div class="dato-fiscal"><div><div class="et">Código de recepción</div><div class="vl">{{ $factura->codigo_recepcion ?: '—' }}</div></div><div><div class="et">Transacción</div><div class="vl">{{ $factura->transaccion ?: '—' }}</div></div></div>
  <div class="dato-fiscal"><div><div class="et">Tipo de emisión</div><div class="vl">{{ $factura->tipo_emision == 2 ? '2 — Contingencia (CAFC)' : '1 — En línea' }}</div></div><div><div class="et">CAFC</div><div class="vl">{{ $factura->cafc ?: '—' }}</div></div></div>
  @if($factura->observaciones_sin)
    <div class="dato-fiscal"><div style="grid-column:1/-1;"><div class="et">Observaciones SIN</div><div class="vl">{{ $factura->observaciones_sin }}</div></div></div>
  @endif

  <div class="no-imprimir" style="display:flex; justify-content:flex-end; margin-top:14px;">
    <div style="text-align:center;">
      <div style="font-size:10px;">Verificación SIN</div>
      <img style="width:120px; height:120px;" src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode('https://siat.impuestos.gob.bo/consulta/QR?cuf=' . $factura->cuf) }}" alt="QR">
    </div>
  </div>
</div>

<div class="card-giseca no-imprimir" style="max-width:640px; margin:14px auto 0; padding:0; overflow:hidden;">
  <div style="padding:12px 18px; display:flex; justify-content:space-between; align-items:center;">
    <strong style="font-size:13px;">Notas de débito / crédito ({{ $factura->notas->count() }})</strong>
    @if($factura->estado === 'emitida')
      @can('facturas.emitir')<button class="btn-giseca btn-outline btn-sm" onclick="document.getElementById('modalNota').classList.add('abierto')"><i class="bi bi-plus"></i> Emitir nota</button>@endcan
    @endif
  </div>
  <table class="tabla-giseca">
    <tbody>
      @forelse($factura->notas as $n)
        <tr>
          <td><span class="codigo-chip">{{ $n->numero }}</span> <span class="estado {{ $n->tipo === 'debito' ? 'estado-rechazada' : 'estado-enviada' }}">{{ strtoupper($n->tipo) }}</span></td>
          <td>{{ \Illuminate\Support\Str::limit($n->motivo, 50) }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($n->monto) }}</td>
        </tr>
      @empty
        <tr><td style="color:var(--gc-gris-claro); font-size:12.5px;">Sin notas registradas.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($factura->estado === 'emitida')
<div class="modal-giseca no-imprimir" id="modalNota">
  <div class="modal-box">
    <h6>Emitir Nota de Débito / Crédito</h6>
    <form method="POST" action="{{ route('facturas.notas.store', $factura) }}">
      @csrf
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Tipo</label><select name="tipo" class="form-control-giseca"><option value="debito">Débito (aumenta el monto)</option><option value="credito">Crédito (descuento/devolución)</option></select></div>
        <div><label class="form-label-giseca">Monto</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control-giseca" required></div>
        <div><label class="form-label-giseca">Motivo</label><input name="motivo" class="form-control-giseca" placeholder="Ej: Descuento por pronto pago" required></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Emitir nota</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalNota').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endif

@if($factura->estado === 'emitida')
<div class="modal-giseca no-imprimir" id="modalAnular">
  <div class="modal-box">
    <h6>Anular Factura ante el SIN</h6>
    <form method="POST" action="{{ route('facturas.anular', $factura) }}">
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
@endif

<div class="modal-giseca no-imprimir" id="modalCorreo">
  <div class="modal-box">
    <h6>Enviar Factura por Correo Electrónico</h6>
    <p style="font-size:12px; color:var(--gc-gris); margin-bottom:12px;">Se enviará el documento PDF oficial y el archivo XML firmado adjuntos al cliente.</p>
    <form method="POST" action="{{ route('facturas.enviar-correo', $factura) }}">
      @csrf
      <div>
        <label class="form-label-giseca">Correo electrónico del destinatario *</label>
        <input type="email" name="email" class="form-control-giseca" value="{{ $factura->venta?->cliente?->correo }}" placeholder="ejemplo@cliente.com" required>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario"><i class="bi bi-send"></i> Enviar correo con adjuntos</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalCorreo').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
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
</script>
@endpush
