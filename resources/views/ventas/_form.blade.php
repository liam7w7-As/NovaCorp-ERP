{{-- Parcial: formulario de venta. Variables: $action, $method, $venta=null, $clientes, $fechaHoy --}}
@if ($errors->any())
    <div
        style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
        {{ $errors->first() }}</div>
@endif
@if (session('error'))
    <div
        style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
        {{ session('error') }}</div>
@endif

<form method="POST" action="{{ $action }}" id="formVenta">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="card-giseca" style="margin-bottom:16px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div>
                <label class="form-label-giseca">Cliente *</label>
                <select id="v_cliente" name="cliente_id" class="form-control-giseca"
                    data-tomselect="{{ route('clientes.buscar') }}" placeholder="Escribe para buscar cliente...">
                    <option value="">— Seleccionar —</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}"
                            {{ (isset($venta) && $venta->cliente_id == $c->id) || old('cliente_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label-giseca">Nuevo cliente (opcional)</label><input id="v_clienteNuevo"
                    name="cliente_nuevo" class="form-control-giseca" placeholder="Escribe para crear uno nuevo"
                    value="{{ old('cliente_nuevo') }}"></div>
            <div><label class="form-label-giseca">Tipo</label>
                <select id="v_tipo" name="tipo" class="form-control-giseca">
                    <option value="con_factura"
                        {{ (isset($venta) ? $venta->tipo : old('tipo', 'sin_factura')) === 'con_factura' ? 'selected' : '' }}>
                        Con factura</option>
                    <option value="sin_factura"
                        {{ (isset($venta) ? $venta->tipo : old('tipo', 'sin_factura')) === 'sin_factura' ? 'selected' : '' }}>
                        Sin factura</option>
                </select>
            </div>
            <div><label class="form-label-giseca">Modalidad</label>
                <select id="v_modalidad" name="modalidad" class="form-control-giseca">
                    <option value="contado"
                        {{ (isset($venta) ? $venta->modalidad : old('modalidad', 'contado')) === 'contado' ? 'selected' : '' }}>
                        Contado</option>
                    <option value="credito"
                        {{ (isset($venta) ? $venta->modalidad : old('modalidad')) === 'credito' ? 'selected' : '' }}>
                        Crédito</option>
                </select>
            </div>
            <div><label class="form-label-giseca">Fecha</label><input type="date" id="v_fecha" name="fecha"
                    class="form-control-giseca"
                    value="{{ isset($venta) ? $venta->fecha->format('Y-m-d') : old('fecha', $fechaHoy ?? date('Y-m-d')) }}"
                    required></div>
            <div><label class="form-label-giseca">Forma de pago</label>
                <select id="v_metodo" name="metodo" class="form-control-giseca">
                    @foreach (['Efectivo', 'Transferencia', 'QR', 'Tarjeta', 'Cheque'] as $m)
                        <option {{ old('metodo', 'Efectivo') === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="margin-top:10px;"><label class="form-label-giseca">Observaciones</label><input id="v_obs"
                name="observaciones" class="form-control-giseca"
                value="{{ isset($venta) ? $venta->observaciones : old('observaciones') }}"></div>
    </div>

    <div class="card-giseca" style="margin-bottom:16px;">
        <div style="position:relative;">
            <label class="form-label-giseca">Agregar producto</label>
            <input type="text" id="v_buscador" class="form-control-giseca"
                placeholder="Buscar por código o descripción..." autocomplete="off">
            <div id="resultadosBusquedaVenta"
                style="position:absolute; background:var(--gc-superficie); border:1px solid var(--gc-borde); border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,.12); width:100%; max-width:500px; max-height:240px; overflow-y:auto; z-index:50; margin-top:4px;">
            </div>
        </div>

        <table class="tabla-giseca" style="margin-top:12px;" id="tablaItemsVenta">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th style="width:80px">Cant.</th>
                    <th style="width:110px">Precio</th>
                    <th style="width:100px">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @if (isset($venta))
                    @foreach ($venta->detalles as $i => $d)
                        <tr>
                            <td><span class="codigo-chip">{{ $d->codigo_producto }}</span><input type="hidden"
                                    name="items[{{ $i }}][producto_id]"
                                    value="{{ $d->producto_id }}"><input type="hidden" class="vi-desc"
                                    value="{{ $d->descripcion_producto }}"></td>
                            <td>{{ $d->descripcion_producto }}</td>
                            <td>
                                <input type="number" step="1" min="1"
                                    name="items[{{ $i }}][cantidad]" value="{{ intval($d->cantidad) }}"
                                    class="vi-cant" oninput="recalcularVenta()"
                                    style="border:1px solid var(--gc-borde); 
        border-radius:4px; 
        padding:5px 6px; 
        font-size:12.5px; 
        width:100%; 
        background:var(--gc-superficie); 
        color:var(--gc-texto);">
                            </td>
                            <td><input type="number" step="0.01" min="0"
                                    name="items[{{ $i }}][precio]" value="{{ $d->precio_unitario }}"
                                    class="vi-precio" oninput="recalcularVenta()"
                                    style="border:1px solid var(--gc-borde); border-radius:4px; padding:5px 6px; font-size:12.5px; width:100%; background:var(--gc-superficie); color:var(--gc-texto);">
                            </td>
                            <td class="text-end vi-total">{{ number_format($d->subtotal, 2) }}</td>
                            <td><button type="button" class="btn-giseca btn-outline btn-icon btn-sm"
                                    onclick="this.closest('tr').remove(); reindexarVenta(); recalcularVenta();">✕</button>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <div style="display:flex; justify-content:flex-end; margin-top:10px;">
            <div style="width:260px;">
                <div style="display:flex; justify-content:space-between;"><span>Subtotal:</span><strong
                        id="v_lblSubtotal">0.00</strong></div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Descuento:</span><input type="number" id="v_descuento" name="descuento"
                        value="{{ isset($venta) ? $venta->descuento : old('descuento', 0) }}" min="0"
                        step="0.01" oninput="recalcularVenta()"
                        style="width:90px; border:1px solid var(--gc-borde); border-radius:4px; padding:4px; text-align:right;">
                </div>
                <div
                    style="display:flex; justify-content:space-between; border-top:2px solid var(--gc-texto); padding-top:6px; margin-top:6px;">
                    <strong>Total:</strong><strong id="v_lblTotal" style="color:var(--gc-primario-oscuro);">Bs
                        0.00</strong>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:8px;">
        <button type="submit" class="btn-giseca btn-primario">Guardar Venta</button>
        <a href="{{ route('ventas.index') }}" class="btn-giseca btn-outline">Cancelar</a>
    </div>
</form>

<script>
    let idxVenta = {{ isset($venta) ? $venta->detalles->count() : 0 }};
    const buscadorVenta = document.getElementById('v_buscador');
    const resultadosVenta = document.getElementById('resultadosBusquedaVenta');
    let timerVenta = null;

    buscadorVenta.addEventListener('input', function() {
        clearTimeout(timerVenta);
        const t = this.value.trim();
        if (t.length < 2) {
            resultadosVenta.innerHTML = '';
            return;
        }
        timerVenta = setTimeout(async () => {
            const r = await fetch("{{ route('productos.buscar') }}?q=" + encodeURIComponent(t), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const lista = await r.json();
            resultadosVenta.innerHTML = lista.map(p =>
                `<div class="item"
data-id="${p.id}"
data-codigo="${p.codigo}"
data-interno="${p.codigo_interno || ''}"
data-desc="${p.descripcion.replace(/"/g, '&quot;')}"
data-precio="${p.precio}"
data-stock="${p.stock}"

style="padding:9px 14px; cursor:pointer; border-bottom:1px solid var(--gc-borde); font-size:13px;">

<strong style="color:var(--gc-primario);">
${p.codigo_interno || 'SIN COD'}
</strong>

<br>

<span>
${p.codigo}
</span>
-
${p.descripcion}

<span style="color:var(--gc-gris-claro);">
Stock: ${p.stock}
</span>

</div>`
            ).join('') || '<div class="item" style="padding:9px 14px;">Sin resultados</div>';
            resultadosVenta.querySelectorAll('.item[data-id]').forEach(el => {
                el.addEventListener('click', () => agregarItemVenta({
                    id: el.dataset.id,
                    codigo: el.dataset.codigo,
                    codigo_interno: el.dataset.interno,
                    descripcion: el.dataset.desc,
                    precio: el.dataset.precio
                }));
            });
        }, 250);
    });
    document.addEventListener('click', e => {
        if (!buscadorVenta.contains(e.target) && !resultadosVenta.contains(e.target)) resultadosVenta
            .innerHTML = '';
    });

    function agregarItemVenta(p) {

        const tbody = document.querySelector('#tablaItemsVenta tbody');

        const tr = document.createElement('tr');

        tr.innerHTML = `
    
    <td>

        <span class="codigo-chip">
            ${p.codigo_interno || ''}
        </span>

        <br>

        <small>
            ${p.codigo}
        </small>


        <input type="hidden"
            name="items[${idxVenta}][producto_id]"
            value="${p.id}">


        <input type="hidden"
            class="vi-desc"
            value="${p.descripcion}">

    </td>


    <td>
        ${p.descripcion}
    </td>


    <td>
        <input type="number"
            step="1"
            min="1"
            name="items[${idxVenta}][cantidad]"
            value="1"
            class="vi-cant"
            oninput="recalcularVenta()"
            style="border:1px solid var(--gc-borde);
            border-radius:4px;
            padding:5px 6px;
            font-size:12.5px;
            width:100%;
            background:var(--gc-superficie);
            color:var(--gc-texto);">
    </td>


    <td>
        <input type="number"
            step="0.01"
            min="0"
            name="items[${idxVenta}][precio]"
            value="${p.precio}"
            class="vi-precio"
            oninput="recalcularVenta()"
            style="border:1px solid var(--gc-borde);
            border-radius:4px;
            padding:5px 6px;
            font-size:12.5px;
            width:100%;
            background:var(--gc-superficie);
            color:var(--gc-texto);">
    </td>


    <td class="text-end vi-total">
        ${Number(p.precio).toFixed(2)}
    </td>


    <td>
        <button type="button"
            class="btn-giseca btn-outline btn-icon btn-sm"
            onclick="this.closest('tr').remove(); reindexarVenta(); recalcularVenta();">
            ✕
        </button>
    </td>

    `;


        tbody.appendChild(tr);


        idxVenta++;


        resultadosVenta.innerHTML = '';

        buscadorVenta.value = '';


        recalcularVenta();

    }

    function reindexarVenta() {
        document.querySelectorAll('#tablaItemsVenta tbody tr').forEach((tr, i) => {
            tr.querySelectorAll('input[name^="items["]').forEach(inp => {
                inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            });
        });
        idxVenta = document.querySelectorAll('#tablaItemsVenta tbody tr').length;
    }

    function recalcularVenta() {
        let subtotal = 0;
        document.querySelectorAll('#tablaItemsVenta tbody tr').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.vi-cant').value) || 0;
            const precio = parseFloat(tr.querySelector('.vi-precio').value) || 0;
            const total = cant * precio;
            tr.querySelector('.vi-total').textContent = total.toFixed(2);
            subtotal += total;
        });
        const desc = parseFloat(document.getElementById('v_descuento').value) || 0;
        document.getElementById('v_lblSubtotal').textContent = subtotal.toFixed(2);
        document.getElementById('v_lblTotal').textContent = 'Bs ' + (subtotal - desc).toFixed(2);
    }

    document.getElementById('formVenta').addEventListener('submit', function(e) {
        const cli = document.getElementById('v_cliente').value || document.getElementById('v_clienteNuevo')
            .value.trim();
        if (!cli) {
            e.preventDefault();
            alert('Selecciona o escribe un cliente.');
            return;
        }
        if (!document.querySelectorAll('#tablaItemsVenta tbody tr').length) {
            e.preventDefault();
            alert('Agrega al menos un producto.');
            return;
        }
    });

    recalcularVenta();
</script>
