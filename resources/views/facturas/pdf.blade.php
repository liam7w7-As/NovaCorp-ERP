<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $factura->numero_factura }}</title>
<style>
  body{ font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; margin: 15px; }
  .header{ width: 100%; border-bottom: 2px solid #1F2A44; padding-bottom: 10px; margin-bottom: 12px; }
  .header td{ vertical-align: top; }
  .empresa{ font-size: 16px; font-weight: bold; color: #1F2A44; }
  .titulo{ font-size: 16px; font-weight: bold; text-align: right; color: #1F2A44; }
  .numero-fac{ font-size: 14px; font-weight: bold; color: #E85D04; }
  table.items{ width: 100%; border-collapse: collapse; margin: 10px 0; }
  table.items th{ background: #1F2A44; color: #fff; border-bottom: 1px solid #ccc; text-align: left; padding: 6px; font-size: 9.5px; }
  table.items td{ border-bottom: 1px solid #ddd; padding: 5px 6px; }
  .totales{ width: 280px; margin-left: auto; border-collapse: collapse; }
  .totales td{ padding: 4px 6px; }
  .total-row td{ border-top: 2px solid #1F2A44; font-weight: bold; font-size: 13px; color: #1F2A44; }
  .leyenda{ font-size: 9px; background: #F7F8FA; border: 1px solid #ddd; padding: 8px; margin: 10px 0; text-align: center; }
  .fiscal{ width: 100%; border-collapse: collapse; font-size: 9px; margin-top: 10px; }
  .fiscal td{ padding: 4px 6px; vertical-align: top; border: 1px solid #eee; }
  .et{ font-size: 8px; font-weight: bold; color: #666; text-transform: uppercase; display: block; }
  .cuf-val{ font-family: monospace; font-size: 8.5px; word-break: break-all; }
  .firmas{ width: 100%; margin-top: 50px; }
  .firmas td{ text-align: center; font-size: 9.5px; border-top: 1px solid #333; padding-top: 4px; }
</style>
</head>
<body>
<table class="header">
  <tr>
    <td style="width: 60%;">
      <div class="empresa">{{ $empresa['nombre'] ?? 'GISECA SRL' }}</div>
      <div><strong>NIT:</strong> {{ \App\Services\SiatConfig::get('siat_nit', '—') }}</div>
      @if($factura->sucursal)
        <div><strong>{{ $factura->sucursal->nombre }}</strong> (Sucursal {{ $factura->codigo_sucursal }})</div>
        <div>{{ $factura->sucursal->direccion }}</div>
        <div>{{ $factura->sucursal->municipio ?? 'Santa Cruz' }} - Bolivia</div>
      @else
        <div><strong>Casa Matriz</strong> (Sucursal 0)</div>
        <div>{{ \App\Services\SiatConfig::get('siat_direccion', $empresa['direccion'] ?? '') }}</div>
      @endif
      <div>Tel: {{ $factura->sucursal?->telefono ?: ($empresa['telefono'] ?? '—') }} · {{ $empresa['email'] ?? '' }}</div>
      @if($factura->puntoVenta)
        <div style="font-size: 8.5px; color: #555;">Punto de Venta: {{ $factura->puntoVenta->nombre }} (Cod: {{ $factura->codigo_punto_venta }})</div>
      @endif
    </td>
    <td style="width: 40%; text-align: right;">
      <div class="titulo">FACTURA</div>
      <div class="numero-fac">N° {{ $factura->numero_factura }}</div>
      <div>Emisión: {{ $factura->fecha_emision->format('d/m/Y H:i') }}</div>
      <div>Venta Ref: {{ $factura->venta->numero ?? '—' }}</div>
      @if($factura->simulada)<div style="color: #DC2626; font-weight: bold;">[SIMULADA]</div>@endif
    </td>
  </tr>
</table>

<table style="width: 100%; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 6px 8px; margin-bottom: 10px;">
  <tr>
    <td><strong>Señor(es):</strong> {{ $factura->venta->cliente_nombre ?? '—' }}</td>
    <td style="text-align: right;"><strong>NIT/CI:</strong> {{ $factura->venta->nit_cliente ?? '—' }}</td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th style="width: 10%;">Cant.</th>
      <th style="width: 50%;">Descripción</th>
      <th style="width: 20%; text-align:right;">P.Unit.</th>
      <th style="width: 20%; text-align:right;">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($factura->venta->detalles ?? [] as $it)
      <tr>
        <td>{{ number_format((float)$it->cantidad, 2) }}</td>
        <td>{{ $it->descripcion_producto }} ({{ $it->codigo_producto }})</td>
        <td style="text-align:right;">{{ formatoMoneda($it->precio_unitario) }}</td>
        <td style="text-align:right;">{{ formatoMoneda($it->subtotal) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<table style="width: 100%;">
  <tr>
    <td style="vertical-align: top; width: 55%;">
      <p style="font-size:9.5px; margin: 0 0 4px;"><strong>Son:</strong> {{ montoALetras($factura->venta->total ?? 0) }}</p>
      <div style="font-size: 8.5px; color: #666;">Documento fiscal digital emitido conforme a normativa del SIN.</div>
    </td>
    <td style="width: 45%;">
      <table class="totales">
        <tr><td>Subtotal:</td><td style="text-align:right;">{{ formatoMoneda($factura->venta->subtotal ?? 0) }}</td></tr>
        <tr><td>Descuento:</td><td style="text-align:right;">{{ formatoMoneda($factura->venta->descuento ?? 0) }}</td></tr>
        <tr class="total-row"><td>TOTAL Bs:</td><td style="text-align:right;">{{ formatoMoneda($factura->venta->total ?? 0) }}</td></tr>
      </table>
    </td>
  </tr>
</table>

<div class="leyenda">"{{ $factura->leyenda }}"</div>

<table class="fiscal">
  <tr>
    <td style="width: 40%;">
      <span class="et">Código de Autorización (CUF)</span>
      <div class="cuf-val">{{ $factura->cuf }}</div>
    </td>
    <td style="width: 40%;">
      <span class="et">CUFD</span>
      <div class="cuf-val">{{ $factura->cufd ?: '—' }}</div>
    </td>
    <td rowspan="2" style="width: 20%; text-align: center; vertical-align: middle;">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=85x85&data={{ urlencode('https://siat.impuestos.gob.bo/consulta/QR?nit='.\App\Services\SiatConfig::get('siat_nit', '0').'&cuf='.$factura->cuf.'&numero='.$factura->numero_factura.'&t=2') }}" width="85" height="85" alt="QR Fiscal" style="display:block; margin:0 auto;">
    </td>
  </tr>
  <tr>
    <td>
      <span class="et">Código de recepción</span>
      <div>{{ $factura->codigo_recepcion ?: '—' }}</div>
    </td>
    <td>
      <span class="et">Tipo Emisión / CAFC</span>
      <div>{{ $factura->tipo_emision == 2 ? 'Contingencia CAFC: '.($factura->cafc ?: '—') : 'En línea (1)' }}</div>
    </td>
  </tr>
</table>

<table class="firmas">
  <tr><td style="border:none;"></td></tr>
  <tr><td width="45%">Entregué Conforme</td><td width="10%" style="border:none;"></td><td width="45%">Recibí Conforme</td></tr>
</table>
</body>
</html>
