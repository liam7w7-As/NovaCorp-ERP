<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $factura->numero_factura }} - Medio Oficio</title>
<style>
  @page { margin: 15px 18px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1F2A44; line-height: 1.25; }
  .header { width: 100%; border-bottom: 2px solid #1F2A44; padding-bottom: 6px; margin-bottom: 8px; }
  .header td { vertical-align: top; }
  .empresa-nombre { font-size: 13px; font-weight: bold; color: #1F2A44; text-transform: uppercase; }
  .empresa-info { font-size: 8.5px; color: #444; }
  .factura-titulo { font-size: 13px; font-weight: bold; text-align: right; color: #1F2A44; }
  .factura-num { font-size: 12px; font-weight: bold; color: #E85D04; }
  .sucursal-badge { font-size: 8px; background: #EEF2F6; padding: 2px 5px; border-radius: 3px; display: inline-block; margin-top: 2px; }
  .datos-cliente { width: 100%; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 6px 8px; margin-bottom: 8px; font-size: 8.5px; }
  .datos-cliente td { padding: 1px 4px; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  table.items th { background: #1F2A44; color: #fff; font-size: 8px; text-transform: uppercase; padding: 4px; text-align: left; }
  table.items td { border-bottom: 1px solid #E2E8F0; padding: 3px 4px; font-size: 8.5px; }
  .text-end { text-align: right; }
  .totales-seccion { width: 100%; margin-bottom: 6px; }
  .totales-seccion td { vertical-align: top; }
  .caja-totales { width: 180px; margin-left: auto; border: 1px solid #CBD5E1; }
  .caja-totales td { padding: 2px 6px; font-size: 8.5px; }
  .fila-total { background: #1F2A44; color: #fff; font-weight: bold; font-size: 10px; }
  .fila-total td { padding: 3px 6px; }
  .monto-letras { font-size: 8px; font-style: italic; color: #475569; margin-top: 2px; }
  .leyenda { font-size: 7.5px; background: #F1F5F9; border: 1px solid #E2E8F0; padding: 4px 6px; text-align: center; margin-bottom: 6px; }
  .fiscal-grid { width: 100%; border-collapse: collapse; font-size: 7.5px; }
  .fiscal-grid td { padding: 2px 4px; border: 1px solid #E2E8F0; vertical-align: top; }
  .etiqueta { font-size: 6.5px; font-weight: bold; color: #64748B; text-transform: uppercase; display: block; }
  .valor-cuf { word-break: break-all; font-family: monospace; font-size: 7px; }
  .qr-box { text-align: center; width: 75px; vertical-align: middle; }
</style>
</head>
<body>

@if(! empty($logoPath ?? null))
  <div style="position:absolute; top:10px; left:0; width:100%; text-align:center;">
    <img src="{{ $logoPath }}" height="27" alt="Logo">
  </div>
@endif
<table class="header">
  <tr>
    <td style="width: 58%;">
      <div class="empresa-nombre">{{ $empresa['nombre'] ?? 'GISECA SRL' }}</div>
      <div class="empresa-info"><strong>NIT:</strong> {{ \App\Services\SiatConfig::get('siat_nit', '—') }}</div>
      <div class="empresa-info">
        @if($factura->sucursal)
          <strong>{{ $factura->sucursal->nombre }}</strong> (Sucursal {{ $factura->codigo_sucursal }})<br>
          {{ $factura->sucursal->direccion }} · {{ $factura->sucursal->municipio ?? 'Santa Cruz' }}
        @else
          <strong>Casa Matriz</strong> (Sucursal 0)<br>
          {{ \App\Services\SiatConfig::get('siat_direccion', $empresa['direccion'] ?? '') }}
        @endif
      </div>
      <div class="empresa-info">Tel: {{ $factura->sucursal?->telefono ?: ($empresa['telefono'] ?? '—') }}</div>
      @if($factura->puntoVenta)
        <div class="sucursal-badge">Punto de Venta: {{ $factura->puntoVenta->nombre }} (Cod: {{ $factura->codigo_punto_venta }})</div>
      @endif
    </td>
    <td style="width: 42%; text-align: right;">
      <div class="factura-titulo">FACTURA</div>
      <div class="factura-num">N° {{ $factura->numero_factura }}</div>
      <div class="empresa-info" style="margin-top: 3px;">
        <strong>Emisión:</strong> {{ $factura->fecha_emision->format('d/m/Y H:i') }}<br>
        <strong>Modalidad:</strong> En Línea<br>
        <strong>Venta Ref:</strong> {{ $factura->venta->numero ?? '—' }}
        @if($factura->simulada)<br><span style="color:#DC2626; font-weight:bold;">[FACTURA SIMULADA]</span>@endif
      </div>
    </td>
  </tr>
</table>

<table class="datos-cliente">
  <tr>
    <td style="width: 65%;"><strong>Señor(es):</strong> {{ $factura->venta->cliente_nombre ?? 'Sin Nombre' }}</td>
    <td style="width: 35%;"><strong>NIT / CI:</strong> {{ $factura->venta->nit_cliente ?? '0' }}</td>
  </tr>
  <tr>
    <td><strong>Dirección / Ciudad:</strong> {{ $factura->venta->cliente?->direccion ?: '—' }}</td>
    <td><strong>Teléfono / Email:</strong> {{ $factura->venta->cliente?->telefono ?: ($factura->venta->cliente?->correo ?: '—') }}</td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th style="width: 10%;">Cant.</th>
      <th style="width: 50%;">Descripción</th>
      <th class="text-end" style="width: 20%;">P. Unit (Bs)</th>
      <th class="text-end" style="width: 20%;">Subtotal (Bs)</th>
    </tr>
  </thead>
  <tbody>
    @foreach($factura->venta->detalles ?? [] as $it)
      <tr>
        <td>{{ number_format((float)$it->cantidad, 2) }}</td>
        <td>
          {{ $it->descripcion_producto }}
          <span style="color:#64748B; font-size:7.5px;">[{{ $it->codigo_producto }}]</span>
        </td>
        <td class="text-end">{{ formatoMoneda($it->precio_unitario) }}</td>
        <td class="text-end">{{ formatoMoneda($it->subtotal) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<table class="totales-seccion">
  <tr>
    <td style="width: 55%; padding-right: 8px;">
      <div class="monto-letras"><strong>Son:</strong> {{ montoALetras($factura->venta->total ?? 0) }}</div>
      <div style="font-size: 7.5px; color: #64748B; margin-top: 4px;">
        Documento fiscal emitido conforme a la normativa del Servicio de Impuestos Nacionales de Bolivia.
      </div>
    </td>
    <td style="width: 45%;">
      <table class="caja-totales" align="right">
        <tr>
          <td>Subtotal Bs:</td>
          <td class="text-end">{{ formatoMoneda($factura->venta->subtotal ?? 0) }}</td>
        </tr>
        <tr>
          <td>Descuento Bs:</td>
          <td class="text-end">{{ formatoMoneda($factura->venta->descuento ?? 0) }}</td>
        </tr>
        <tr class="fila-total">
          <td>TOTAL Bs:</td>
          <td class="text-end">{{ formatoMoneda($factura->venta->total ?? 0) }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="leyenda">
  "{{ $factura->leyenda }}"
</div>

<table class="fiscal-grid">
  <tr>
    <td>
      <span class="etiqueta">Código de Autorización (CUF)</span>
      <span class="valor-cuf">{{ $factura->cuf }}</span>
    </td>
    <td>
      <span class="etiqueta">Código Recepción SIN</span>
      <span>{{ $factura->codigo_recepcion ?: '—' }}</span>
    </td>
    <td rowspan="2" class="qr-box">
      <img src="{{ $qrUrl }}" width="115" height="115" alt="QR Fiscal" style="display:block; margin: 0 auto;">
    </td>
  </tr>
  <tr>
    <td>
      <span class="etiqueta">Código Único de Facturación Diaria (CUFD)</span>
      <span class="valor-cuf">{{ $factura->cufd ?: '—' }}</span>
    </td>
    <td>
      <span class="etiqueta">Tipo Emisión / CAFC</span>
      <span>{{ $factura->tipo_emision == 2 ? 'Contingencia CAFC: '.($factura->cafc ?: '—') : 'En línea (1)' }}</span>
    </td>
  </tr>
</table>

</body>
</html>
