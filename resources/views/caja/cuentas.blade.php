@extends('layouts.app')

@section('title', 'Cuentas por Cobrar y Pagar')

@section('content')
<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px;">
  <div class="kpi azul"><div class="label"><i class="bi bi-hourglass-split"></i> Por cobrar (ventas a crédito)</div><div class="value">Bs {{ formatoMoneda($totalCobrar) }}</div></div>
  <div class="kpi naranja"><div class="label"><i class="bi bi-exclamation-circle"></i> Por pagar (compras)</div><div class="value">Bs {{ formatoMoneda($totalPagar) }}</div></div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start;">
  <div class="card-giseca" style="padding:0; overflow:hidden;">
    <div style="padding:12px 18px; font-weight:700;">Por cobrar</div>
    <table class="tabla-giseca">
      <thead><tr><th>Venta</th><th>Cliente</th><th class="text-end">Saldo</th><th></th></tr></thead>
      <tbody>
        @forelse($porCobrar as $v)
          <tr>
            <td><span class="codigo-chip">{{ $v->numero }}</span><div style="font-size:10.5px; color:var(--gc-gris-claro);">{{ $v->fecha->format('Y-m-d') }}</div></td>
            <td>{{ \Illuminate\Support\Str::limit($v->cliente_nombre, 22) }}</td>
            <td class="text-end fw-bold">Bs {{ formatoMoneda($v->saldo) }}</td>
            <td class="text-end"><button class="btn-giseca btn-outline btn-sm" onclick="abrirCobro('{{ route('cuentas.cobrar', $v) }}', '{{ $v->numero }}', '{{ $v->saldo }}')">Cobrar</button></td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:26px;">Sin saldos por cobrar.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-giseca" style="padding:0; overflow:hidden;">
    <div style="padding:12px 18px; font-weight:700;">Por pagar</div>
    <table class="tabla-giseca">
      <thead><tr><th>Compra</th><th>Proveedor</th><th class="text-end">Saldo</th><th></th></tr></thead>
      <tbody>
        @forelse($porPagar as $c)
          <tr>
            <td><span class="codigo-chip">{{ $c->numero }}</span><div style="font-size:10.5px; color:var(--gc-gris-claro);">{{ $c->fecha->format('Y-m-d') }}</div></td>
            <td>{{ \Illuminate\Support\Str::limit($c->proveedor_nombre, 22) }}</td>
            <td class="text-end fw-bold">Bs {{ formatoMoneda($c->saldo) }}</td>
            <td class="text-end"><button class="btn-giseca btn-outline btn-sm" onclick="abrirPago('{{ route('cuentas.pagar', $c) }}', '{{ $c->numero }}', '{{ $c->saldo }}')">Pagar</button></td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:26px;">Sin saldos por pagar.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal-giseca" id="modalMov">
  <div class="modal-box">
    <h6 id="movTitulo">Registrar</h6>
    <form method="POST" action="" id="formMov">
      @csrf
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Monto (saldo: Bs <span id="movSaldo"></span>)</label><input type="number" step="0.01" min="0.01" name="monto" id="movMonto" class="form-control-giseca" required></div>
        <div><label class="form-label-giseca">Forma de pago</label><select name="metodo" class="form-control-giseca"><option>Efectivo</option><option>Transferencia</option><option>QR</option><option>Tarjeta</option><option>Cheque</option></select></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Guardar</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalMov').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function abrirCobro(url, numero, saldo) {
  document.getElementById('formMov').action = url;
  document.getElementById('movTitulo').textContent = 'Cobrar venta ' + numero;
  document.getElementById('movSaldo').textContent = saldo;
  document.getElementById('movMonto').value = saldo;
  document.getElementById('modalMov').classList.add('abierto');
}
function abrirPago(url, numero, saldo) {
  document.getElementById('formMov').action = url;
  document.getElementById('movTitulo').textContent = 'Pagar compra ' + numero;
  document.getElementById('movSaldo').textContent = saldo;
  document.getElementById('movMonto').value = saldo;
  document.getElementById('modalMov').classList.add('abierto');
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
