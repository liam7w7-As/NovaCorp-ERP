{{-- Parcial: formulario de compra. Variables: $action, $method ('POST'|'PUT'), $compra=null, $proveedores, $fechaHoy --}}
@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif
@if(session('error'))
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ $action }}" id="formCompra"
  @if($method === 'POST')
    data-confirm="Se registrará la compra y aumentará el stock de los productos incluidos. ¿Los datos son correctos?"
    data-confirm-title="Confirmar compra"
    data-confirm-label="Registrar compra"
  @endif>
  @csrf
  @if($method === 'PUT') @method('PUT') @endif

  <div class="card-giseca" style="margin-bottom:16px;">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
      <div>
        <label class="form-label-giseca">Proveedor *</label>
        <select id="c_proveedor" name="proveedor_id" class="form-control-giseca" data-tomselect="{{ route('proveedores.buscar') }}" placeholder="Escribe para buscar proveedor...">
          <option value="">— Seleccionar —</option>
          @foreach($proveedores as $p)
            <option value="{{ $p->id }}" {{ (isset($compra) && $compra->proveedor_id == $p->id) || old('proveedor_id') == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div><label class="form-label-giseca">Nuevo proveedor (opcional)</label><input id="c_proveedorNuevo" name="proveedor_nuevo" class="form-control-giseca" placeholder="Escribe para crear uno nuevo" value="{{ old('proveedor_nuevo') }}"></div>
      <div><label class="form-label-giseca">Tipo</label>
        <select id="c_tipo" name="tipo" class="form-control-giseca">
          <option value="con_factura" {{ (isset($compra) ? $compra->tipo : old('tipo', 'con_factura')) === 'con_factura' ? 'selected' : '' }}>Con factura</option>
          <option value="sin_factura" {{ (isset($compra) ? $compra->tipo : old('tipo')) === 'sin_factura' ? 'selected' : '' }}>Sin factura</option>
        </select>
      </div>
      <div><label class="form-label-giseca">Fecha</label><input type="date" id="c_fecha" name="fecha" class="form-control-giseca" value="{{ isset($compra) ? $compra->fecha->format('Y-m-d') : old('fecha', $fechaHoy ?? date('Y-m-d')) }}" required></div>
      <div><label class="form-label-giseca">Modalidad</label>
        <select id="c_modalidad" name="modalidad" class="form-control-giseca">
          <option value="contado" {{ (isset($compra) ? $compra->modalidad : old('modalidad', 'contado')) === 'contado' ? 'selected' : '' }}>Contado</option>
          <option value="credito" {{ (isset($compra) ? $compra->modalidad : old('modalidad')) === 'credito' ? 'selected' : '' }}>Crédito (queda por pagar)</option>
        </select>
      </div>
      <div><label class="form-label-giseca">Forma de pago</label>
        <select id="c_metodo" name="metodo" class="form-control-giseca">
          @foreach(['Efectivo','Transferencia','QR','Tarjeta','Cheque'] as $m)
            <option {{ old('metodo', 'Efectivo') === $m ? 'selected' : '' }}>{{ $m }}</option>
          @endforeach
        </select>
      </div>
      <div><label class="form-label-giseca">Observaciones</label><input id="c_obs" name="observaciones" class="form-control-giseca" value="{{ isset($compra) ? $compra->observaciones : old('observaciones') }}"></div>
    </div>
  </div>

  <div class="card-giseca" style="margin-bottom:16px;">
    <div style="position:relative;">
      <label class="form-label-giseca">Agregar producto</label>
      <input type="text" id="c_buscador" class="form-control-giseca" placeholder="Buscar por código o descripción..." autocomplete="off">
      <div id="resultadosBusquedaCompra" style="position:absolute; background:var(--gc-superficie); border:1px solid var(--gc-borde); border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,.12); width:100%; max-width:500px; max-height:240px; overflow-y:auto; z-index:50; margin-top:4px;"></div>
    </div>

    <table class="tabla-giseca" style="margin-top:12px;" id="tablaItemsCompra">
      <thead><tr><th>Código</th><th>Descripción</th><th style="width:80px">Cant.</th><th style="width:110px">Costo</th><th style="width:100px">Total</th><th></th></tr></thead>
      <tbody>
        @if(isset($compra))
          @foreach($compra->detalles as $i => $d)
            <tr>
              <td><span class="codigo-chip">{{ $d->codigo_producto }}</span><input type="hidden" name="items[{{ $i }}][producto_id]" value="{{ $d->producto_id }}"><input type="hidden" class="ci-desc" value="{{ $d->descripcion_producto }}"></td>
              <td>{{ $d->descripcion_producto }}</td>
              <td><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][cantidad]" value="{{ $d->cantidad }}" class="ci-cant" oninput="recalcularCompra()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%; background:var(--gc-superficie); color:var(--gc-texto);"></td>
              <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][costo]" value="{{ $d->precio_unitario }}" class="ci-costo" oninput="recalcularCompra()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%; background:var(--gc-superficie); color:var(--gc-texto);"></td>
              <td class="text-end ci-total">{{ number_format($d->subtotal, 2) }}</td>
              <td><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" onclick="this.closest('tr').remove(); reindexarCompra(); recalcularCompra();">✕</button></td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>

    <div style="display:flex; justify-content:flex-end; margin-top:10px;">
      <div style="width:260px;">
        <div style="display:flex; justify-content:space-between;"><span>Subtotal:</span><strong id="c_lblSubtotal">0.00</strong></div>
        <div style="display:flex; justify-content:space-between; align-items:center;"><span>Descuento:</span><input type="number" id="c_descuento" name="descuento" value="{{ isset($compra) ? $compra->descuento : old('descuento', 0) }}" min="0" step="0.01" oninput="recalcularCompra()" style="width:90px; border:1px solid var(--gc-borde); border-radius:4px; padding:4px; text-align:right;"></div>
        <div style="display:flex; justify-content:space-between; border-top:2px solid var(--gc-texto); padding-top:6px; margin-top:6px;"><strong>Total:</strong><strong id="c_lblTotal" style="color:var(--gc-primario-oscuro);">Bs 0.00</strong></div>
      </div>
    </div>
  </div>

  <div style="display:flex; gap:8px;">
    <button type="submit" class="btn-giseca btn-primario">Guardar Compra</button>
    <a href="{{ route('compras.index') }}" class="btn-giseca btn-outline">Cancelar</a>
  </div>
</form>

<script>
let idxCompra = {{ isset($compra) ? $compra->detalles->count() : 0 }};
const buscadorCompra = document.getElementById('c_buscador');
const resultadosCompra = document.getElementById('resultadosBusquedaCompra');
let timerCompra = null;

buscadorCompra.addEventListener('input', function() {
  clearTimeout(timerCompra);
  const t = this.value.trim();
  if (t.length < 2) { resultadosCompra.innerHTML = ''; return; }
  timerCompra = setTimeout(async () => {
    const r = await fetch("{{ route('productos.buscar') }}?q=" + encodeURIComponent(t), { headers: { 'Accept': 'application/json' } });
    const lista = await r.json();
    resultadosCompra.innerHTML = lista.map(p =>
      `<div class="item" data-id="${p.id}" data-codigo="${p.codigo}" data-desc="${p.descripcion.replace(/"/g, '&quot;')}" data-costo="${p.costo}" style="padding:9px 14px; cursor:pointer; border-bottom:1px solid var(--gc-borde); font-size:13px;"><strong>${p.codigo}</strong> — ${p.descripcion}</div>`
    ).join('') || '<div class="item" style="padding:9px 14px;">Sin resultados</div>';
    resultadosCompra.querySelectorAll('.item[data-id]').forEach(el => {
      el.addEventListener('click', () => agregarItemCompra({ id: el.dataset.id, codigo: el.dataset.codigo, descripcion: el.dataset.desc, costo: el.dataset.costo }));
    });
  }, 250);
});
document.addEventListener('click', e => {
  if (!buscadorCompra.contains(e.target) && !resultadosCompra.contains(e.target)) resultadosCompra.innerHTML = '';
});

function agregarItemCompra(p) {
  const tbody = document.querySelector('#tablaItemsCompra tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><span class="codigo-chip">${p.codigo}</span><input type="hidden" name="items[${idxCompra}][producto_id]" value="${p.id}"><input type="hidden" class="ci-desc" value="${p.descripcion}"></td>
    <td>${p.descripcion}</td>
    <td><input type="number" step="0.01" min="0.01" name="items[${idxCompra}][cantidad]" value="1" class="ci-cant" oninput="recalcularCompra()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%; background:var(--gc-superficie); color:var(--gc-texto);"></td>
    <td><input type="number" step="0.01" min="0" name="items[${idxCompra}][costo]" value="${p.costo}" class="ci-costo" oninput="recalcularCompra()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%; background:var(--gc-superficie); color:var(--gc-texto);"></td>
    <td class="text-end ci-total">${Number(p.costo).toFixed(2)}</td>
    <td><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" onclick="this.closest('tr').remove(); reindexarCompra(); recalcularCompra();">✕</button></td>`;
  tbody.appendChild(tr);
  idxCompra++;
  resultadosCompra.innerHTML = ''; buscadorCompra.value = '';
  recalcularCompra();
}

function reindexarCompra() {
  document.querySelectorAll('#tablaItemsCompra tbody tr').forEach((tr, i) => {
    tr.querySelectorAll('input[name^="items["]').forEach(inp => {
      inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + i + ']');
    });
  });
  idxCompra = document.querySelectorAll('#tablaItemsCompra tbody tr').length;
}

function recalcularCompra() {
  let subtotal = 0;
  document.querySelectorAll('#tablaItemsCompra tbody tr').forEach(tr => {
    const cant = parseFloat(tr.querySelector('.ci-cant').value) || 0;
    const costo = parseFloat(tr.querySelector('.ci-costo').value) || 0;
    const total = cant * costo;
    tr.querySelector('.ci-total').textContent = total.toFixed(2);
    subtotal += total;
  });
  const desc = parseFloat(document.getElementById('c_descuento').value) || 0;
  document.getElementById('c_lblSubtotal').textContent = subtotal.toFixed(2);
  document.getElementById('c_lblTotal').textContent = 'Bs ' + (subtotal - desc).toFixed(2);
}

document.getElementById('formCompra').addEventListener('submit', function(e) {
  const prov = document.getElementById('c_proveedor').value || document.getElementById('c_proveedorNuevo').value.trim();
  if (!prov) { e.preventDefault(); GisecaDialog.alert('Selecciona un proveedor registrado o escribe el nombre de uno nuevo.', { titulo: 'Falta el proveedor' }); return; }
  if (!document.querySelectorAll('#tablaItemsCompra tbody tr').length) { e.preventDefault(); GisecaDialog.alert('Agrega al menos un producto antes de registrar la compra.', { titulo: 'Compra sin productos' }); return; }
});

recalcularCompra();
</script>
