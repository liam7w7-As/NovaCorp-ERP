<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Proforma {{ $proforma->numero }}</title>
<style>
  body{ font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 20px; }
  .header{ width: 100%; border-bottom: 2px solid #1F2A44; padding-bottom: 10px; margin-bottom: 12px; }
  .header td{ vertical-align: top; }
  .empresa{ font-size: 16px; font-weight: bold; color: #1F2A44; }
  .titulo{ font-size: 16px; font-weight: bold; text-align: right; color: #1F2A44; }
  table.items{ width: 100%; border-collapse: collapse; margin: 10px 0; }
  table.items th{ background: #1F2A44; color: #fff; text-align: left; padding: 6px; font-size: 9.5px; }
  table.items td{ border-bottom: 1px solid #ddd; padding: 5px 6px; }
  .totales{ width: 280px; margin-left: auto; border-collapse: collapse; }
  .totales td{ padding: 4px 6px; }
  .total-row td{ border-top: 2px solid #1F2A44; font-weight: bold; font-size: 13px; color: #1F2A44; }
</style>
</head>
<body>
@if(! empty($logoPath ?? null))
  <div style="position:absolute; top:12px; left:0; width:100%; text-align:center;">
    <img src="{{ $logoPath }}" height="30" alt="Logo">
  </div>
@endif
<table class="header">
  <tr>
    <td style="width: 60%;">
      <div class="empresa">{{ $empresa['nombre'] ?? 'GISECA SRL' }}</div>
      @if(! empty($empresa['direccion']))<div>{{ $empresa['direccion'] }}</div>@endif
      @if(! empty($empresa['telefono']))<div>Tel: {{ $empresa['telefono'] }}</div>@endif
      @if(! empty($empresa['email']))<div>{{ $empresa['email'] }}</div>@endif
    </td>
    <td style="width: 40%; text-align: right;">
      <div class="titulo">PROFORMA</div>
      <div style="font-size: 14px; font-weight: bold;">N° {{ $proforma->numero }}</div>
      <div>Emisión: {{ $proforma->fecha->format('d/m/Y') }}</div>
      <div>Válida hasta: {{ $proforma->validez ? $proforma->validez->format('d/m/Y') : '—' }}</div>
    </td>
  </tr>
</table>

<table style="width: 100%; margin-bottom: 10px;">
  <tr>
    <td><strong>Cliente:</strong> {{ $proforma->cliente_nombre }}</td>
    <td><strong>Teléfono:</strong> {{ $proforma->telefono ?: '—' }}</td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th style="width: 6%;">N°</th>
      <th style="width: 16%;">Cód. Interno</th>
      <th style="width: 14%;">Código</th>
      <th>Descripción</th>
      <th style="width: 10%; text-align:right;">Cant.</th>
      <th style="width: 14%; text-align:right;">P.Unit.</th>
      <th style="width: 14%; text-align:right;">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($proforma->detalles as $i => $it)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $it->codigo_interno ?? $it->producto?->codigo_interno ?? '—' }}</td>
        <td>{{ $it->codigo_producto }}</td>
        <td>{{ $it->descripcion_producto }}</td>
        <td style="text-align:right;">{{ $it->cantidad }}</td>
        <td style="text-align:right;">{{ formatoMoneda($it->precio_unitario) }}</td>
        <td style="text-align:right;">{{ formatoMoneda($it->subtotal) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<table style="width: 100%;">
  <tr>
    <td style="vertical-align: top; width: 55%; font-size: 10px;">
      <strong>Entrega:</strong> {{ $proforma->tiempo_entrega ?: '—' }}<br>
      <strong>Pago:</strong> {{ $proforma->condiciones_pago ?: '—' }}<br>
      <strong>Garantía:</strong> {{ $proforma->garantia ?: '—' }}<br>
      @if($proforma->nota)<strong>Observaciones:</strong> {{ $proforma->nota }}@endif
    </td>
    <td style="width: 45%;">
      <table class="totales">
        <tr><td>Subtotal:</td><td style="text-align:right;">{{ formatoMoneda($proforma->subtotal) }}</td></tr>
        <tr><td>Descuento:</td><td style="text-align:right;">{{ \App\Services\Descuentos::etiqueta((float) $proforma->subtotal, (float) $proforma->descuento, $proforma->descuento_tipo ?? 'fijo') }}</td></tr>
        <tr class="total-row"><td>TOTAL Bs:</td><td style="text-align:right;">{{ formatoMoneda($proforma->total) }}</td></tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
