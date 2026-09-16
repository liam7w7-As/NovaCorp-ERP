<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $comprobante->numero }} - GISECA SRL</title>
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
<style>
  body{ background:#fff; }
  .comprobante-formal{ background:#fff; border:1px solid #ddd; border-radius:6px; overflow:hidden; font-size:12.5px; color:#222; max-width:480px; margin:20px auto; }
  .comprobante-formal .banner{ background:#5f6368; color:#fff; padding:14px 18px; text-align:center; }
  .comprobante-formal .banner .empresa{ font-weight:800; font-size:14px; letter-spacing:.3px; }
  .comprobante-formal .banner .tipo{ font-size:12px; font-weight:600; margin-top:2px; }
  .comprobante-formal .cuerpo{ padding:16px 18px; }
  .comprobante-formal .fila-campos{ display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; padding:9px 0; border-bottom:1px solid #ddd; }
  .comprobante-formal .campo .etiqueta{ font-size:10px; font-weight:700; color:#666; text-transform:uppercase; letter-spacing:.3px; }
  .comprobante-formal .campo .valor{ font-size:12.5px; margin-top:2px; word-break:break-word; }
  .comprobante-formal .caja-monto{ background:#f5f5f5; border:1px solid #ddd; border-radius:4px; padding:10px 14px; margin:14px 0; }
  .comprobante-formal .caja-monto .fila-monto{ display:flex; justify-content:space-between; align-items:baseline; }
  .comprobante-formal .caja-monto .etq{ font-size:10.5px; font-weight:700; color:#666; text-transform:uppercase; }
  .comprobante-formal .caja-monto .num{ font-size:17px; font-weight:800; }
  .comprobante-formal .caja-monto .letras{ font-size:10px; color:#666; margin-top:3px; }
  .comprobante-formal .caja-concepto{ border:1px solid #ddd; border-radius:4px; padding:8px 12px; margin-bottom:14px; }
  .comprobante-formal .caja-concepto .etq{ font-size:10px; font-weight:700; color:#666; text-transform:uppercase; margin-bottom:2px; }
  .comprobante-formal .desglose-formal{ margin-bottom:14px; }
  .comprobante-formal .desglose-formal table{ width:100%; font-size:11px; border-collapse:collapse; }
  .comprobante-formal .desglose-formal th{ text-align:left; font-size:9.5px; text-transform:uppercase; color:#666; padding:4px 6px; border-bottom:1px solid #ddd; }
  .comprobante-formal .desglose-formal td{ padding:5px 6px; border-bottom:1px solid #ddd; }
  .comprobante-formal .firmas{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:40px; text-align:center; font-size:11px; }
  .comprobante-formal .firmas .linea{ border-top:1px solid #333; padding-top:5px; }
  @page{ size: letter; margin: 1.6cm; }
  @media print{
    .no-imprimir{ display:none !important; }
    .comprobante-formal{ max-width:100%; width:100%; margin:0; border:none; border-radius:0; font-size:14px; min-height:24cm; display:flex; flex-direction:column; }
    .comprobante-formal .banner{ padding:26px 30px; }
    .comprobante-formal .banner .empresa{ font-size:24px; }
    .comprobante-formal .banner .tipo{ font-size:17px; margin-top:4px; }
    .comprobante-formal .cuerpo{ padding:34px 40px; flex-grow:1; display:flex; flex-direction:column; }
    .comprobante-formal .caja-monto .num{ font-size:26px; }
    .comprobante-formal .firmas{ margin-top:auto; padding-top:80px; font-size:13px; }
  }
</style>
</head>
<body onload="window.print()">
<div class="no-imprimir" style="text-align:center; padding:12px;">
  <button onclick="window.print()" style="padding:8px 18px; border-radius:6px; border:1px solid #ccc; cursor:pointer;">Imprimir</button>
  <a href="{{ route('comprobantes.show', $comprobante) }}" style="margin-left:8px;">Volver</a>
</div>
@if(!empty($membretado) && $membretado['tipo'] === 'image')
<div style="max-width:480px; margin:20px auto 0; text-align:center;">
  <img src="{{ $membretado['url'] }}" style="max-width:100%; border-radius:4px;" alt="Membretado">
</div>
@endif
@include('comprobantes._documento', ['comprobante' => $comprobante])
</body>
</html>
