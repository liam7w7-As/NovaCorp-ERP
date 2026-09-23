<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $factura->numero_factura }} - Rollo 80mm</title>
<style>
  @page { margin: 4px 6px; }
  body { font-family: DejaVu Sans, monospace; font-size: 8.5px; color: #000; margin: 0; padding: 2px; line-height: 1.25; }
  .c { text-align: center; }
  .r { text-align: right; }
  .b { font-weight: bold; }
  .titulo { font-size: 11px; font-weight: bold; text-transform: uppercase; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 2px 0; vertical-align: top; }
  .linea { border-top: 1px dashed #222; margin: 5px 0; }
  .total { font-size: 11px; font-weight: bold; }
  .peq { font-size: 7.5px; word-break: break-all; }
  .qr-center { text-align: center; margin: 5px 0; }
</style>
</head>
<body>

<div class="c">
  <div class="titulo">{{ $empresa['nombre'] ?? 'GISECA SRL' }}</div>
  <div>NIT: {{ \App\Services\SiatConfig::get('siat_nit', '—') }}</div>
  @if($factura->sucursal)
    <div>{{ $factura->sucursal->nombre }} (Sucursal {{ $factura->codigo_sucursal }})</div>
    <div>{{ $factura->sucursal->direccion }}</div>
    <div>{{ $factura->sucursal->municipio ?? 'Santa Cruz' }} - Bolivia</div>
  @else
    <div>Casa Matriz (Sucursal 0)</div>
    <div>{{ \App\Services\SiatConfig::get('siat_direccion', $empresa['direccion'] ?? '') }}</div>
  @endif
  <div>Tel: {{ $factura->sucursal?->telefono ?: ($empresa['telefono'] ?? '—') }}</div>
  @if($factura->puntoVenta)
    <div>Punto de Venta: {{ $factura->puntoVenta->nombre }} (Cod: {{ $factura->codigo_punto_venta }})</div>
  @endif
</div>

<div class="linea"></div>

<div class="c">
  <strong style="font-size:10px;">FACTURA N° {{ $factura->numero_factura }}</strong>
  @if($factura->simulada)<div class="b">[FACTURA SIMULADA]</div>@endif
</div>
<div>Fecha: {{ $factura->fecha_emision->format('d/m/Y H:i') }}</div>
<div>Señor(es): {{ $factura->venta->cliente_nombre ?? '—' }}</div>
<div>NIT/CI: {{ $factura->venta->nit_cliente ?? '—' }}</div>

<div class="linea"></div>

<table>
  <tr>
    <td class="b" style="width:22%;">CANT.</td>
    <td class="b">ARTÍCULO</td>
    <td class="b r" style="width:28%;">SUBTOTAL</td>
  </tr>
  @foreach($factura->venta->detalles ?? [] as $it)
    <tr>
      <td>{{ number_format((float)$it->cantidad, 2) }}</td>
      <td>{{ $it->descripcion_producto }}<br><span style="font-size:7.5px;">{{ $it->codigo_producto }} · {{ formatoMoneda($it->precio_unitario) }} c/u</span></td>
      <td class="r">{{ formatoMoneda($it->subtotal) }}</td>
    </tr>
  @endforeach
</table>

<div class="linea"></div>

<table>
  <tr><td>Subtotal:</td><td class="r">{{ formatoMoneda($factura->venta->subtotal ?? 0) }}</td></tr>
  <tr><td>Descuento:</td><td class="r">{{ formatoMoneda($factura->venta->descuento ?? 0) }}</td></tr>
  <tr class="total"><td>TOTAL Bs:</td><td class="r">{{ formatoMoneda($factura->venta->total ?? 0) }}</td></tr>
</table>
<div style="font-size:7.5px; margin-top:2px;">Son: {{ montoALetras($factura->venta->total ?? 0) }}</div>

<div class="linea"></div>

<div class="peq"><strong>CUF:</strong> {{ $factura->cuf }}</div>
<div class="peq"><strong>CUFD:</strong> {{ $factura->cufd ?: '—' }}</div>
<div class="peq"><strong>Recepción:</strong> {{ $factura->codigo_recepcion ?: '—' }}</div>

<div class="linea"></div>

<div class="qr-center">
  <img src="{{ $qrUrl }}" width="115" height="115" alt="QR de verificación SIN">
</div>

<div class="c" style="font-size:7.5px; margin-top:2px;">
  "{{ $factura->leyenda }}"
</div>
<div class="c" style="font-size:7px; margin-top:3px;">
  Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea.
</div>
<div class="c b" style="margin-top:6px; font-size:8px;">*** GRACIAS POR SU COMPRA ***</div>

</body>
</html>
