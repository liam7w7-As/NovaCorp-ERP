@extends('layouts.app')

@section('title', 'Comprobantes de Ingreso y Egreso')

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
  .gc-fila-lista-detalle{ display:grid; grid-template-columns:1.2fr 1fr; gap:18px; }
  .gc-columna-detalle{ width:100%; }
  .fila-pago{ display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--gc-borde); font-size:12.5px; }
  .fila-pago:last-child{ border-bottom:none; }
  .barra-saldo{ height:6px; border-radius:4px; background:var(--gc-borde); overflow:hidden; margin-top:6px; }
  .barra-saldo .relleno{ height:100%; background:var(--gc-verde); }
</style>
@endpush

@section('content')
<div class="no-imprimir" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('comprobantes.index') }}" style="margin:0; display:flex; gap:8px;">
    <select name="tipo" class="form-control-giseca" style="width:170px;" onchange="this.form.submit()">
      <option value="">Todos</option>
      <option value="ingreso" {{ $tipo === 'ingreso' ? 'selected' : '' }}>Ingresos</option>
      <option value="egreso" {{ $tipo === 'egreso' ? 'selected' : '' }}>Egresos</option>
    </select>
    <input type="text" name="q" class="form-control-giseca" style="width:220px;" placeholder="Buscar N° o concepto..." value="{{ $q }}">
  </form>
  <button class="btn-giseca btn-primario" onclick="document.getElementById('modalNuevo').classList.add('abierto')"><i class="bi bi-plus-lg"></i> Nuevo Comprobante</button>
</div>

<div class="no-imprimir" style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px;">
  <div class="kpi verde"><div class="label">Total Ingresos</div><div class="value">Bs {{ formatoMoneda($kpis['ingresos']) }}</div></div>
  <div class="kpi naranja"><div class="label">Total Egresos</div><div class="value">Bs {{ formatoMoneda($kpis['egresos']) }}</div></div>
  <div class="kpi azul"><div class="label">Balance</div><div class="value">Bs {{ formatoMoneda($kpis['balance']) }}</div></div>
</div>

@if($errors->any())
  <div class="no-imprimir" style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="gc-fila-lista-detalle">
  <div class="card-giseca no-imprimir" style="padding:0; overflow:hidden; align-self:start;">
    <table class="tabla-giseca">
      <thead><tr><th>N°</th><th>Tipo</th><th>Beneficiario / Cliente</th><th>Concepto</th><th class="text-end">Monto</th><th>Pago</th><th></th></tr></thead>
      <tbody>
        @forelse($comprobantes as $c)
          <tr style="cursor:pointer; {{ $seleccionado && $seleccionado->id === $c->id ? 'background:var(--gc-primario-suave);' : '' }}" onclick="window.location='{{ route('comprobantes.index', ['ver' => $c->id, 'tipo' => $tipo, 'q' => $q]) }}'">
            <td><span class="codigo-chip {{ $c->tipo === 'ingreso' ? 'equivalente' : '' }}">{{ $c->numero }}</span></td>
            <td><span class="estado {{ $c->tipo === 'ingreso' ? 'estado-aprobada' : 'estado-rechazada' }}">{{ strtoupper($c->tipo) }}</span></td>
            <td style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{!! $c->entidad ? e($c->entidad) : '<span style="color:var(--gc-rojo); font-style:italic;">Sin definir</span>' !!}</td>
            <td style="max-width:170px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $c->concepto }}</td>
            <td class="text-end fw-bold">Bs {{ formatoMoneda($c->monto) }}</td>
            <td>@if($c->pagos->count() > 1)<span class="estado estado-enviada">{{ $c->pagos->count() }} formas</span>@else<span style="font-size:11.5px; color:var(--gc-gris-claro);">{{ $c->metodo }}</span>@endif</td>
            <td class="text-end" onclick="event.stopPropagation();">
              <a class="btn-giseca btn-outline btn-icon btn-sm" href="{{ route('comprobantes.show', $c) }}"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align:center; color:var(--gc-gris-claro); padding:30px;">Sin comprobantes.</td></tr>
        @endforelse
      </tbody>
    </table>
    @if($comprobantes->hasPages())
      <div style="display:flex; justify-content:flex-end; gap:8px; padding:10px 14px; border-top:1px solid var(--gc-borde);">
        @if(!$comprobantes->onFirstPage())<a class="btn-giseca btn-outline btn-sm" href="{{ $comprobantes->previousPageUrl() }}">← Anterior</a>@endif
        @if($comprobantes->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $comprobantes->nextPageUrl() }}">Siguiente →</a>@endif
      </div>
    @endif
  </div>

  <div class="gc-columna-detalle">
    @if($seleccionado)
      @include('comprobantes._documento', ['comprobante' => $seleccionado])
      @php
        $asig = $seleccionado->pagos->sum(fn($p) => (float) $p->monto);
        $saldo = round((float) $seleccionado->monto - $asig, 2);
        $pct = (float) $seleccionado->monto > 0 ? min(100, round($asig / (float) $seleccionado->monto * 100)) : 100;
      @endphp
      <div class="card-giseca no-imprimir" style="max-width:480px; margin:14px auto 0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <strong style="font-size:12px;">Desglose de forma de pago</strong>
          <button class="btn-giseca btn-outline btn-sm" onclick="document.getElementById('modalPago').classList.add('abierto')"><i class="bi bi-plus"></i> Agregar</button>
        </div>
        @foreach($seleccionado->pagos as $p)
          <div class="fila-pago">
            <span><i class="bi bi-cash"></i> {{ ucfirst($p->forma_pago) }}@if($p->banco) — {{ $p->banco }}@endif</span>
            <span style="display:flex; align-items:center; gap:8px;"><strong>Bs {{ formatoMoneda($p->monto) }}</strong>
              @if($seleccionado->pagos->count() > 1)
                <form method="POST" action="{{ route('comprobantes.pagos.destroy', [$seleccionado, $p]) }}" style="display:inline;" data-confirm="¿Quitar esta forma de pago del comprobante?" data-confirm-title="Quitar forma de pago" data-confirm-label="Quitar" data-confirm-variant="peligro">@csrf @method('DELETE')<button class="btn-giseca btn-outline btn-icon btn-sm"><i class="bi bi-x"></i></button></form>
              @endif
            </span>
          </div>
        @endforeach
        <div class="barra-saldo"><div class="relleno" style="width:{{ $pct }}%; background:{{ $saldo == 0 ? 'var(--gc-verde)' : ($saldo < 0 ? 'var(--gc-rojo)' : 'var(--gc-amarillo)') }};"></div></div>
        <div style="font-size:11.5px; color:var(--gc-gris-claro); margin-top:4px; text-align:right;">
          @if($saldo == 0) ✓ Totalmente distribuido @elseif($saldo > 0) Bs {{ formatoMoneda($saldo) }} sin asignar todavía @else Bs {{ formatoMoneda(abs($saldo)) }} de más (revisar montos) @endif
        </div>
      </div>
      <div class="no-imprimir" style="display:flex; gap:8px; max-width:480px; margin:14px auto 0;">
        <a class="btn-giseca btn-oscuro" style="flex:1; justify-content:center;" href="{{ route('comprobantes.imprimir', $seleccionado) }}" target="_blank"><i class="bi bi-printer"></i> Imprimir</a>
        <a class="btn-giseca btn-outline" href="{{ route('comprobantes.edit', $seleccionado) }}"><i class="bi bi-pencil"></i> Editar</a>
        @if($seleccionado->es_manual)
          @can('admin')<form method="POST" action="{{ route('comprobantes.destroy', $seleccionado) }}" style="display:inline;" data-confirm="¿Eliminar este comprobante manual?" data-confirm-title="Eliminar comprobante" data-confirm-label="Eliminar" data-confirm-variant="peligro">@csrf @method('DELETE')<button class="btn-giseca btn-outline"><i class="bi bi-trash" style="color:var(--gc-rojo);"></i></button></form>@endcan
        @endif
      </div>
    @else
      <div class="no-imprimir" style="text-align:center; color:var(--gc-gris-claro); padding:40px;">Selecciona un comprobante de la lista para verlo.</div>
    @endif
  </div>
</div>

<!-- Modal: Nuevo comprobante manual -->
<div class="modal-giseca no-imprimir" id="modalNuevo">
  <div class="modal-box">
    <h6>Nuevo Comprobante Manual</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Para movimientos de caja que no vienen de una compra o venta (aportes, gastos varios, retiros, etc.).</p>
    <form method="POST" action="{{ route('comprobantes.store') }}">
      @csrf
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Tipo</label><select id="n_tipo" name="tipo" class="form-control-giseca" onchange="document.getElementById('n_etiquetaEntidad').textContent = this.value === 'egreso' ? 'Beneficiario (a quién se le paga) *' : 'Recibido de (quién nos paga) *'"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
        <div><label class="form-label-giseca" id="n_etiquetaEntidad">Recibido de *</label><input name="entidad" class="form-control-giseca" placeholder="Nombre de la persona o empresa" required></div>
        <div><label class="form-label-giseca">Concepto</label><input name="concepto" class="form-control-giseca" placeholder="Ej: Pago de alquiler, aporte de capital..." required></div>
        <div><label class="form-label-giseca">Monto</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control-giseca" required></div>
        <div><label class="form-label-giseca">Fecha</label><input type="date" name="fecha" class="form-control-giseca" value="{{ date('Y-m-d') }}" required></div>
        <div><label class="form-label-giseca">Forma de pago</label><select name="metodo" class="form-control-giseca"><option>Efectivo</option><option>Transferencia</option><option>QR</option><option>Tarjeta</option><option>Cheque</option></select></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
          <div><label class="form-label-giseca">Banco (opcional)</label><input name="banco" class="form-control-giseca"></div>
          <div><label class="form-label-giseca">Cuenta (opcional)</label><input name="cuenta" class="form-control-giseca"></div>
        </div>
        <div><label class="form-label-giseca">N° de referencia (opcional)</label><input name="numero_referencia" class="form-control-giseca"></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Guardar</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalNuevo').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

@if($seleccionado)
<!-- Modal: Agregar forma de pago -->
<div class="modal-giseca no-imprimir" id="modalPago">
  <div class="modal-box">
    <h6>Agregar Forma de Pago</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Ej: si el total es Bs 1,000 y pagaron Bs 600 por transferencia y Bs 400 en efectivo, agrégalos aquí como dos formas separadas.</p>
    <form method="POST" action="{{ route('comprobantes.pagos.store', $seleccionado) }}">
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
