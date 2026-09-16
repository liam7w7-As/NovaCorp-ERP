{{-- Parcial: formulario de proforma. Variables: $action, $method, $proforma=null, $clientes, $fechaHoy, $fechaValidez --}}
@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif
@if(session('error'))
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ $action }}" id="formProforma">
  @csrf
  @if($method === 'PUT') @method('PUT') @endif

  <div class="card-giseca seccion-form" style="margin-bottom:18px;">
    <h6 style="margin:0 0 14px; border-bottom:2px solid var(--gc-primario); padding-bottom:6px; display:inline-block; font-weight:700;">Datos Generales</h6>
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px;">
      <div>
        <label class="form-label-giseca">Cliente *</label>
        <select id="pf_cliente" name="cliente_id" class="form-control-giseca" data-tomselect="{{ route('clientes.buscar') }}" placeholder="Escribe para buscar cliente...">
          <option value="">-- Seleccionar cliente --</option>
          @foreach($clientes as $c)
            <option value="{{ $c->id }}" {{ (isset($proforma) && $proforma->cliente_id == $c->id) || old('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }} ({{ $c->nit ?: 's/n' }})</option>
          @endforeach
        </select>
      </div>
      <div><label class="form-label-giseca">Nuevo cliente (opcional)</label><input id="pf_clienteNuevo" name="cliente_nuevo" class="form-control-giseca" placeholder="Escribe para crear uno nuevo" value="{{ old('cliente_nuevo') }}"></div>
      <div></div>
      <div><label class="form-label-giseca">Persona de contacto</label><input id="pf_contacto" name="contacto" class="form-control-giseca" value="{{ isset($proforma) ? $proforma->contacto : old('contacto') }}"></div>
      <div><label class="form-label-giseca">Teléfono</label><input id="pf_telefono" name="telefono" class="form-control-giseca" value="{{ isset($proforma) ? $proforma->telefono : old('telefono') }}"></div>
      <div></div>
      <div><label class="form-label-giseca">Fecha de emisión</label><input type="date" id="pf_fecha" name="fecha" class="form-control-giseca" value="{{ isset($proforma) ? $proforma->fecha->format('Y-m-d') : old('fecha', $fechaHoy ?? date('Y-m-d')) }}" required></div>
      <div><label class="form-label-giseca">Fecha de validez</label><input type="date" id="pf_validez" name="validez" class="form-control-giseca" value="{{ isset($proforma) && $proforma->validez ? $proforma->validez->format('Y-m-d') : old('validez', $fechaValidez ?? '') }}"></div>
    </div>
  </div>

  <div class="card-giseca seccion-form" style="margin-bottom:18px; position:relative;">
    <h6 style="margin:0 0 14px; border-bottom:2px solid var(--gc-primario); padding-bottom:6px; display:inline-block; font-weight:700;">Productos</h6>
    <input type="text" id="pf_buscador" class="form-control-giseca" placeholder="Buscar por código, equivalente o descripción..." style="max-width:520px;" autocomplete="off">
    <div id="pf_resultados" style="position:absolute; background:#fff; border:1px solid var(--gc-borde); border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,.12); width:520px; max-height:260px; overflow-y:auto; z-index:50; margin-top:4px;"></div>

    <div style="overflow-x:auto; margin-top:14px;">
    <table class="tabla-giseca" id="tablaProductos">
      <thead><tr><th>Código</th><th>Descripción</th><th>Marca</th><th style="width:80px">Cant.</th><th style="width:100px">P. Unit.</th><th style="width:100px">Total</th><th></th></tr></thead>
      <tbody>
        @if(isset($proforma))
          @foreach($proforma->detalles as $i => $d)
            <tr>
              <td><span class="codigo-chip">{{ $d->codigo_producto }}</span><input type="hidden" name="items[{{ $i }}][producto_id]" value="{{ $d->producto_id }}"></td>
              <td>{{ $d->descripcion_producto }}</td>
              <td>{{ $d->producto->marca ?? '' }}</td>
              <td><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][cantidad]" value="{{ $d->cantidad }}" class="pf-cant" oninput="recalcularProforma()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%;"></td>
              <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][precio]" value="{{ $d->precio_unitario }}" class="pf-precio" oninput="recalcularProforma()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%;"></td>
              <td class="text-end pf-total">{{ number_format($d->subtotal, 2) }}</td>
              <td><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" onclick="this.closest('tr').remove(); reindexarProforma(); recalcularProforma();">✕</button></td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
    </div>

    <div style="display:flex; justify-content:flex-end; margin-top:14px;">
      <div style="width:300px;">
        <div style="display:flex; justify-content:space-between; padding:4px 0;"><span>Subtotal:</span><strong id="pf_lblSubtotal">0.00</strong></div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0;"><span>Descuento:</span><input type="number" id="pf_descuento" name="descuento" value="{{ isset($proforma) ? $proforma->descuento : old('descuento', 0) }}" min="0" step="0.01" oninput="recalcularProforma()" style="width:100px; border:1px solid var(--gc-borde); border-radius:4px; padding:5px; text-align:right;"></div>
        <div style="display:flex; justify-content:space-between; padding:8px 0; border-top:2px solid var(--gc-texto); margin-top:6px;"><strong>TOTAL GENERAL:</strong><strong id="pf_lblTotal" style="color:var(--gc-primario-oscuro);">Bs 0.00</strong></div>
      </div>
    </div>
  </div>

  <div class="card-giseca seccion-form" style="margin-bottom:18px;">
    <h6 style="margin:0 0 14px; border-bottom:2px solid var(--gc-primario); padding-bottom:6px; display:inline-block; font-weight:700;">Condiciones Comerciales</h6>
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px;">
      <div><label class="form-label-giseca">Tiempo de entrega</label><input name="tiempo_entrega" class="form-control-giseca" placeholder="Ej: 24-48 horas" value="{{ isset($proforma) ? $proforma->tiempo_entrega : old('tiempo_entrega') }}"></div>
      <div><label class="form-label-giseca">Condiciones de pago</label><input name="condiciones_pago" class="form-control-giseca" placeholder="Ej: 50% anticipo" value="{{ isset($proforma) ? $proforma->condiciones_pago : old('condiciones_pago') }}"></div>
      <div><label class="form-label-giseca">Garantía</label><input name="garantia" class="form-control-giseca" placeholder="Ej: 6 meses" value="{{ isset($proforma) ? $proforma->garantia : old('garantia') }}"></div>
    </div>
    <div style="margin-top:14px;"><label class="form-label-giseca">Observaciones</label><textarea name="nota" class="form-control-giseca" rows="2">{{ isset($proforma) ? $proforma->nota : old('nota') }}</textarea></div>
  </div>

  <div class="card-giseca seccion-form" style="margin-bottom:18px;">
    <h6 style="margin:0 0 14px; border-bottom:2px solid var(--gc-primario); padding-bottom:6px; display:inline-block; font-weight:700;">Reserva de Stock</h6>
    <label style="display:flex; align-items:center; gap:8px; font-size:13.5px;">
      <input type="checkbox" name="reserva_stock" value="1" {{ (isset($proforma) && $proforma->reserva_stock) ? 'checked' : '' }}> Reservar stock para esta proforma (solo anota la intención; el stock real se descuenta al Convertir a Venta)
    </label>
  </div>

  <button type="submit" class="btn-giseca btn-primario" style="padding:11px 22px;"><i class="bi bi-save"></i> Guardar Proforma</button>
  <a href="{{ isset($proforma) ? route('proformas.show', $proforma) : route('proformas.index') }}" class="btn-giseca btn-outline" style="padding:11px 22px;">Cancelar</a>
</form>

<script>
let idxPf = {{ isset($proforma) ? $proforma->detalles->count() : 0 }};
const buscadorPf = document.getElementById('pf_buscador');
const resultadosPf = document.getElementById('pf_resultados');
let timerPf = null;

buscadorPf.addEventListener('input', function() {
  clearTimeout(timerPf);
  const t = this.value.trim();
  if (t.length < 2) { resultadosPf.innerHTML = ''; return; }
  timerPf = setTimeout(async () => {
    const r = await fetch("{{ route('proformas.buscar-producto') }}?q=" + encodeURIComponent(t), { headers: { 'Accept': 'application/json' } });
    const lista = await r.json();
    resultadosPf.innerHTML = lista.map(p =>
      `<div class="item" data-id="${p.id}" data-codigo="${p.codigo}" data-desc="${p.descripcion.replace(/"/g, '&quot;')}" data-marca="${(p.marca || '').replace(/"/g, '&quot;')}" data-precio="${p.precio}" data-stock="${p.stock}" style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--gc-borde); font-size:13px;"><strong>${p.codigo}</strong> — ${p.descripcion} <span style="color:var(--gc-gris-claro)">(${p.marca || ''}) Stock: ${p.stock}</span></div>`
    ).join('') || '<div class="item" style="padding:10px 14px;">Sin resultados</div>';
    resultadosPf.querySelectorAll('.item[data-id]').forEach(el => {
      el.addEventListener('click', () => agregarProductoPf({ id: el.dataset.id, codigo: el.dataset.codigo, descripcion: el.dataset.desc, marca: el.dataset.marca, precio: el.dataset.precio }));
    });
  }, 250);
});
document.addEventListener('click', e => {
  if (!buscadorPf.contains(e.target) && !resultadosPf.contains(e.target)) resultadosPf.innerHTML = '';
});

function agregarProductoPf(p) {
  const tbody = document.querySelector('#tablaProductos tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><span class="codigo-chip">${p.codigo}</span><input type="hidden" name="items[${idxPf}][producto_id]" value="${p.id}"></td>
    <td>${p.descripcion}</td>
    <td>${p.marca || ''}</td>
    <td><input type="number" step="0.01" min="0.01" name="items[${idxPf}][cantidad]" value="1" class="pf-cant" oninput="recalcularProforma()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%;"></td>
    <td><input type="number" step="0.01" min="0" name="items[${idxPf}][precio]" value="${p.precio}" class="pf-precio" oninput="recalcularProforma()" style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%;"></td>
    <td class="text-end pf-total">${Number(p.precio).toFixed(2)}</td>
    <td><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" onclick="this.closest('tr').remove(); reindexarProforma(); recalcularProforma();">✕</button></td>`;
  tbody.appendChild(tr);
  idxPf++;
  resultadosPf.innerHTML = ''; buscadorPf.value = '';
  recalcularProforma();
}

function reindexarProforma() {
  document.querySelectorAll('#tablaProductos tbody tr').forEach((tr, i) => {
    tr.querySelectorAll('input[name^="items["]').forEach(inp => {
      inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + i + ']');
    });
  });
  idxPf = document.querySelectorAll('#tablaProductos tbody tr').length;
}

function recalcularProforma() {
  let subtotal = 0;
  document.querySelectorAll('#tablaProductos tbody tr').forEach(tr => {
    const cant = parseFloat(tr.querySelector('.pf-cant').value) || 0;
    const precio = parseFloat(tr.querySelector('.pf-precio').value) || 0;
    const total = cant * precio;
    tr.querySelector('.pf-total').textContent = total.toFixed(2);
    subtotal += total;
  });
  const desc = parseFloat(document.getElementById('pf_descuento').value) || 0;
  document.getElementById('pf_lblSubtotal').textContent = subtotal.toFixed(2);
  document.getElementById('pf_lblTotal').textContent = 'Bs ' + (subtotal - desc).toFixed(2);
}

document.getElementById('formProforma').addEventListener('submit', function(e) {
  const cli = document.getElementById('pf_cliente').value || document.getElementById('pf_clienteNuevo').value.trim();
  if (!cli) { e.preventDefault(); alert('Selecciona o crea un cliente.'); return; }
  if (!document.querySelectorAll('#tablaProductos tbody tr').length) { e.preventDefault(); alert('Agrega al menos un producto.'); return; }
});

recalcularProforma();
</script>
