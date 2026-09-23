@extends('layouts.app')

@section('title', 'Detalle de Proforma')

@push('styles')
<style>
  .pdf-preview{ background:#fff; border:1px solid var(--gc-borde); border-radius:10px; padding:26px; font-size:12.5px; }
  .pdf-header{ display:flex; justify-content:space-between; border-bottom:2px solid var(--gc-texto); padding-bottom:12px; margin-bottom:14px; }
  .qr-real{ width:100px; height:100px; }
  @media print{
    .pdf-preview{ border:none; border-radius:0; padding:0; }
  }
</style>
@endpush

@section('content')
<div class="no-imprimir" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
  <span class="estado estado-{{ $proforma->estado }}" style="font-size:12.5px; padding:5px 14px;">{{ strtoupper($proforma->estado) }}</span>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <a class="btn-giseca btn-oscuro btn-sm" href="{{ route('proformas.pdf', $proforma) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Abrir PDF</a>
    <button class="btn-giseca btn-sm" id="btnWhatsapp" style="background:#25D366; color:#fff;"><i class="bi bi-whatsapp"></i> Enviar por WhatsApp</button>
    @if(!$proforma->esta_convertida)
      <form method="POST" action="{{ route('proformas.cambiar-estado', $proforma) }}" style="display:inline;">@csrf<button name="estado" value="aprobada" class="btn-giseca btn-outline btn-sm">Marcar Aprobada</button></form>
      <form method="POST" action="{{ route('proformas.cambiar-estado', $proforma) }}" style="display:inline;">@csrf<button name="estado" value="rechazada" class="btn-giseca btn-outline btn-sm">Marcar Rechazada</button></form>
      <button class="btn-giseca btn-primario btn-sm" onclick="document.getElementById('modalConvertir').classList.add('abierto')"><i class="bi bi-cart-check"></i> Convertir a Venta</button>
    @else
      <a class="btn-giseca btn-outline btn-sm" href="{{ route('ventas.index', ['q' => $proforma->venta->numero ?? '']) }}">Ver venta {{ $proforma->venta->numero ?? '' }} →</a>
      @if($proforma->venta && $proforma->venta->facturaElectronica && $proforma->venta->facturaElectronica->estado !== 'rechazada')
        <a class="btn-giseca btn-outline btn-sm" href="{{ route('facturas.show', $proforma->venta->facturaElectronica) }}"><i class="bi bi-file-earmark-check"></i> Factura {{ $proforma->venta->facturaElectronica->numero_factura }}</a>
      @endif
    @endif
  </div>
</div>

@if(session('exito'))
  <div class="no-imprimir" style="background:var(--gc-verde-suave); color:var(--gc-verde); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ session('exito') }}</div>
@endif
@if(session('error') || $errors->any())
  <div class="no-imprimir" style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ session('error', $errors->first()) }}</div>
@endif

<div class="pdf-preview" id="pdfPreview">
  <div class="pdf-header">
    <div style="display:flex; gap:12px; align-items:center;">
      <img src="{{ $logo['url'] }}" alt="Logo" style="width:110px;">
      <div>
        <div style="font-weight:800; font-size:18px; color:var(--gc-texto);">{{ $empresa['nombre'] }}</div>
        @if($empresa['direccion'])<div>{{ $empresa['direccion'] }}</div>@endif
        @if($empresa['telefono'])<div>Tel: {{ $empresa['telefono'] }}</div>@endif
        @if($empresa['email'])<div>{{ $empresa['email'] }}</div>@endif
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-weight:800; font-size:16px;">PROFORMA <span class="codigo-chip">{{ $proforma->numero }}</span></div>
      <div>Fecha emisión: {{ $proforma->fecha->format('Y-m-d') }}</div>
      <div>Válida hasta: {{ $proforma->validez ? $proforma->validez->format('Y-m-d') : '—' }}</div>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px;">
    <div><strong>Cliente:</strong> {{ $proforma->cliente_nombre }}</div>
    <div><strong>Contacto:</strong> {{ $proforma->contacto ?: '—' }}</div>
    <div><strong>Teléfono:</strong> {{ $proforma->telefono ?: '—' }}</div>
  </div>

  <table class="tabla-giseca" style="margin-bottom:14px;">
    <thead><tr><th>N°</th><th>Cód. Interno</th><th>Código</th><th>Descripción</th><th>Marca</th><th>Unidad</th>
               <th class="text-end">Cant.</th><th class="text-end">P.Unit.</th><th class="text-end">Total</th></tr></thead>
    <tbody>
      @foreach($proforma->detalles as $i => $it)
        <tr><td>{{ $i + 1 }}</td><td><span class="codigo-chip">{{ $it->codigo_interno ?? $it->producto?->codigo_interno ?? '—' }}</span></td><td><span class="codigo-chip">{{ $it->codigo_producto }}</span></td>
            <td>{{ $it->descripcion_producto }}</td><td>{{ $it->producto->marca ?? '' }}</td><td>{{ $it->producto->unidad ?? 'PZA' }}</td>
            <td class="text-end">{{ $it->cantidad }}</td><td class="text-end">{{ formatoMoneda($it->precio_unitario) }}</td>
            <td class="text-end">{{ formatoMoneda($it->subtotal) }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <div style="display:flex; justify-content:flex-end; margin-bottom:20px;">
    <div style="width:280px;">
      <div style="display:flex; justify-content:space-between; padding:3px 0;"><span>Subtotal:</span><span>{{ formatoMoneda($proforma->subtotal) }}</span></div>
      <div style="display:flex; justify-content:space-between; padding:3px 0;"><span>Descuento:</span><span>{{ \App\Services\Descuentos::etiqueta((float) $proforma->subtotal, (float) $proforma->descuento, $proforma->descuento_tipo ?? 'fijo') }}</span></div>
      <div style="display:flex; justify-content:space-between; padding:6px 0; border-top:2px solid var(--gc-texto); font-weight:800; font-size:16px;">
        <span>TOTAL:</span><span style="color:var(--gc-primario-oscuro);">Bs {{ formatoMoneda($proforma->total) }}</span>
      </div>
    </div>
  </div>

  <div style="font-size:12px; margin-bottom:20px;">
    <strong>Entrega:</strong> {{ $proforma->tiempo_entrega ?: '—' }} &nbsp;|&nbsp; <strong>Pago:</strong> {{ $proforma->condiciones_pago ?: '—' }} &nbsp;|&nbsp; <strong>Garantía:</strong> {{ $proforma->garantia ?: '—' }}<br>
    @if($proforma->nota)<strong>Observaciones:</strong> {{ $proforma->nota }}<br>@endif
    @if($proforma->reserva_stock)<strong style="color:var(--gc-verde);">✓ Stock reservado para esta proforma</strong>@endif
  </div>

  <div class="no-imprimir" style="display:flex; justify-content:flex-end; gap:20px;">
    <div style="text-align:center;">
      <div style="font-size:10px;">Escanear para verificar</div>
      <img class="qr-real" id="qrVerificacion" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(route('proformas.show', $proforma)) }}" alt="QR verificación">
    </div>
  </div>
</div>

@if(!$proforma->esta_convertida)
<div class="modal-giseca no-imprimir" id="modalConvertir">
  <div class="modal-box">
    <h6>Convertir a Venta</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Se creará la venta, se descontará el stock real y se generará el comprobante de ingreso automáticamente.</p>
    <form method="POST" action="{{ route('proformas.convertir-venta', $proforma) }}">
      @csrf
      <div style="margin-bottom:10px;">
        <label class="form-label-giseca">Tipo de comprobante</label>
        <select name="tipo" class="form-control-giseca"><option value="con_factura">Con factura</option><option value="sin_factura" selected>Sin factura</option></select>
      </div>
      <div style="margin-bottom:10px;">
        <label class="form-label-giseca">Modalidad</label>
        <select name="modalidad" class="form-control-giseca"><option value="contado">Contado</option><option value="credito">Crédito</option></select>
      </div>
      <div style="margin-bottom:16px;">
        <label class="form-label-giseca">Forma de pago</label>
        <select name="metodo" class="form-control-giseca"><option>Efectivo</option><option>Transferencia</option><option>QR</option><option>Tarjeta</option><option>Cheque</option></select>
      </div>
      <div style="display:flex; gap:8px;">
        <button class="btn-giseca btn-primario">Confirmar Conversión</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalConvertir').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function(){
  const mensaje = @json("Estimado cliente, adjuntamos la Proforma N° {$proforma->numero} de GISECA SRL por un total de Bs " . formatoMoneda($proforma->total) . ". Puede revisarla en: " . route('proformas.show', $proforma));
  document.getElementById('btnWhatsapp').onclick = () => window.open('https://wa.me/?text=' + encodeURIComponent(mensaje), '_blank');
})();
function mostrarToast(mensaje, tipo) {
  let t = document.getElementById('toastGiseca');
  if (!t) { t = document.createElement('div'); t.id = 'toastGiseca'; t.className = 'toast-giseca'; document.body.appendChild(t); }
  t.textContent = mensaje; t.className = 'toast-giseca mostrar ' + (tipo || '');
  setTimeout(() => t.classList.remove('mostrar'), 2800);
}
</script>
@endpush
