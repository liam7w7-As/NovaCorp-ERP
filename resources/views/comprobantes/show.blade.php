@extends('layouts.app')

@section('title', 'Comprobante ' . $comprobante->numero)

@push('styles')
<style>
  .comprobante-formal{ background:#fff; border:1px solid var(--gc-borde); border-radius:6px; overflow:hidden; font-size:12.5px; color:#222; max-width:480px; margin:0 auto; }
  .comprobante-formal .banner{ background:#5f6368; color:#fff; padding:14px 18px; text-align:center; }
  .comprobante-formal .banner .empresa{ font-weight:800; font-size:14px; letter-spacing:.3px; }
  .comprobante-formal .banner .tipo{ font-size:12px; font-weight:600; margin-top:2px; }
  .comprobante-formal .cuerpo{ padding:16px 18px; }
  .comprobante-formal .fila-campos{ display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; padding:9px 0; border-bottom:1px solid var(--gc-borde); }
  .comprobante-formal .campo .etiqueta{ font-size:10px; font-weight:700; color:var(--gc-gris); text-transform:uppercase; letter-spacing:.3px; }
  .comprobante-formal .campo .valor{ font-size:12.5px; margin-top:2px; word-break:break-word; }
  .comprobante-formal .caja-monto{ background:var(--gc-fondo); border:1px solid var(--gc-borde); border-radius:4px; padding:10px 14px; margin:14px 0; }
  .comprobante-formal .caja-monto .fila-monto{ display:flex; justify-content:space-between; align-items:baseline; }
  .comprobante-formal .caja-monto .etq{ font-size:10.5px; font-weight:700; color:var(--gc-gris); text-transform:uppercase; }
  .comprobante-formal .caja-monto .num{ font-size:17px; font-weight:800; }
  .comprobante-formal .caja-monto .letras{ font-size:10px; color:var(--gc-gris); margin-top:3px; }
  .comprobante-formal .caja-concepto{ border:1px solid var(--gc-borde); border-radius:4px; padding:8px 12px; margin-bottom:14px; }
  .comprobante-formal .caja-concepto .etq{ font-size:10px; font-weight:700; color:var(--gc-gris); text-transform:uppercase; margin-bottom:2px; }
  .comprobante-formal .desglose-formal{ margin-bottom:14px; }
  .comprobante-formal .desglose-formal table{ width:100%; font-size:11px; border-collapse:collapse; }
  .comprobante-formal .desglose-formal th{ text-align:left; font-size:9.5px; text-transform:uppercase; color:var(--gc-gris); padding:4px 6px; border-bottom:1px solid var(--gc-borde); }
  .comprobante-formal .desglose-formal td{ padding:5px 6px; border-bottom:1px solid var(--gc-borde); }
  .comprobante-formal .firmas{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:40px; text-align:center; font-size:11px; }
  .comprobante-formal .firmas .linea{ border-top:1px solid #333; padding-top:5px; }
</style>
@endpush

@section('content')
@if(session('exito'))
  <div style="background:var(--gc-verde-suave); color:var(--gc-verde); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px; max-width:480px; margin-left:auto; margin-right:auto;">{{ session('exito') }}</div>
@endif
@if(!$comprobante->es_manual)
  <div style="max-width:480px; margin:0 auto 14px; font-size:12.5px; color:var(--gc-gris);">
    Generado automáticamente por
    @if($comprobante->origen_compra_id)<a href="{{ route('compras.index', ['q' => $comprobante->referencia]) }}">compra {{ $comprobante->referencia }}</a>@endif
    @if($comprobante->origen_venta_id)<a href="{{ route('ventas.index', ['q' => $comprobante->referencia]) }}">venta {{ $comprobante->referencia }}</a>@endif
  </div>
@endif

@if(!empty($membretado) && $membretado['tipo'] === 'image')
  <div style="max-width:480px; margin:0 auto 14px; text-align:center;">
    <img src="{{ $membretado['url'] }}" style="max-width:100%; border-radius:4px;" alt="Membretado">
  </div>
@endif

@include('comprobantes._documento', ['comprobante' => $comprobante])

<div class="no-imprimir" style="display:flex; gap:8px; max-width:480px; margin:14px auto 0;">
  <a class="btn-giseca btn-oscuro" style="flex:1; justify-content:center;" href="{{ route('comprobantes.imprimir', $comprobante) }}" target="_blank"><i class="bi bi-printer"></i> Imprimir</a>
  <a class="btn-giseca btn-outline" href="{{ route('comprobantes.edit', $comprobante) }}"><i class="bi bi-pencil"></i> Editar</a>
  <a class="btn-giseca btn-outline" href="{{ route('comprobantes.index', ['ver' => $comprobante->id]) }}"><i class="bi bi-list"></i> Lista</a>
</div>

<div class="card-giseca no-imprimir" style="max-width:480px; margin:14px auto 0;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
    <strong style="font-size:12px;">Formas de pago ({{ $comprobante->pagos->count() }})</strong>
    <button class="btn-giseca btn-outline btn-sm" onclick="document.getElementById('modalPago').classList.add('abierto')"><i class="bi bi-plus"></i> Agregar</button>
  </div>
  @foreach($comprobante->pagos as $p)
    <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--gc-borde); font-size:12.5px;">
      <span>{{ ucfirst($p->forma_pago) }}@if($p->banco) — {{ $p->banco }}@endif</span>
      <span style="display:flex; gap:8px; align-items:center;"><strong>Bs {{ formatoMoneda($p->monto) }}</strong>
        @if($comprobante->pagos->count() > 1)
          <form method="POST" action="{{ route('comprobantes.pagos.destroy', [$comprobante, $p]) }}" style="display:inline;" onsubmit="return confirm('¿Quitar esta forma de pago?')">@csrf @method('DELETE')<button class="btn-giseca btn-outline btn-icon btn-sm" style="width:24px;height:24px;"><i class="bi bi-x" style="font-size:11px;"></i></button></form>
        @endif
      </span>
    </div>
  @endforeach
</div>

<div class="modal-giseca no-imprimir" id="modalPago">
  <div class="modal-box">
    <h6>Agregar Forma de Pago</h6>
    <form method="POST" action="{{ route('comprobantes.pagos.store', $comprobante) }}">
      @csrf
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Forma de pago</label><select name="forma_pago" class="form-control-giseca"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="qr">QR</option><option value="tarjeta">Tarjeta</option><option value="cheque">Cheque</option><option value="otro">Otro</option></select></div>
        <div><label class="form-label-giseca">Monto</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control-giseca" required></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
          <div><label class="form-label-giseca">Banco (opcional)</label><input name="banco" class="form-control-giseca"></div>
          <div><label class="form-label-giseca">Cuenta (opcional)</label><input name="cuenta" class="form-control-giseca"></div>
        </div>
        <div><label class="form-label-giseca">N° de referencia (opcional)</label><input name="referencia" class="form-control-giseca"></div>
        <div><label class="form-label-giseca">Nota (opcional)</label><input name="nota" class="form-control-giseca"></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Agregar</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalPago').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endsection
