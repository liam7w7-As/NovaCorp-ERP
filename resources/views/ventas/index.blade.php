@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
    <div class="ventas-kpis">
        <div class="kpi naranja">
            <div class="label"><i class="bi bi-cash-coin"></i> Total vendido</div>
            <div class="value">Bs {{ formatoMoneda($kpis['total']) }}</div>
        </div>
        <div class="kpi verde">
            <div class="label"><i class="bi bi-wallet2"></i> Ventas contado</div>
            <div class="value">Bs {{ formatoMoneda($kpis['contado']) }}</div>
        </div>
        <div class="kpi azul">
            <div class="label"><i class="bi bi-hourglass-split"></i> Ventas crédito</div>
            <div class="value">Bs {{ formatoMoneda($kpis['credito']) }}</div>
        </div>
        <div class="kpi rojo">
            <div class="label"><i class="bi bi-exclamation-triangle"></i> Vencido</div>
            <div class="value">Bs {{ formatoMoneda($kpis['vencido']) }}</div>
        </div>
        <div class="kpi azul">
            <div class="label"><i class="bi bi-wallet2"></i> Saldo por cobrar</div>
            <div class="value">Bs {{ formatoMoneda($kpis['saldo_cobrar']) }}</div>
        </div>
        <div class="kpi verde">
            <div class="label"><i class="bi bi-clipboard-check"></i> Por entregar</div>
            <div class="value">{{ $kpis['por_entregar'] }}</div>
        </div>
        <div class="kpi info">
            <div class="label"><i class="bi bi-receipt-cutoff"></i> Débito fiscal (SIAT)</div>
            <div class="value">Bs {{ formatoMoneda($kpis['debito_fiscal']) }}</div>
        </div>
        <div class="kpi gris">
            <div class="label"><i class="bi bi-receipt"></i> Documentos activos</div>
            <div class="value">{{ $kpis['documentos'] }}</div>
        </div>
    </div>

    <div
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <form method="GET" action="{{ route('ventas.index') }}" style="margin:0; display:flex; gap:8px; flex-wrap:wrap;">
            <select name="tipo" class="form-control-giseca" style="width:150px;" onchange="this.form.submit()">
                <option value="">Todos los tipos</option>
                <option value="con_factura" {{ $tipo === 'con_factura' ? 'selected' : '' }}>Con factura</option>
                <option value="sin_factura" {{ $tipo === 'sin_factura' ? 'selected' : '' }}>Sin factura</option>
            </select>
            <select name="modalidad" class="form-control-giseca" style="width:140px;" onchange="this.form.submit()">
                <option value="">Todas las modalidades</option>
                <option value="contado" {{ $modalidad === 'contado' ? 'selected' : '' }}>Contado</option>
                <option value="credito" {{ $modalidad === 'credito' ? 'selected' : '' }}>Crédito</option>
            </select>
            <select name="sucursal_id" class="form-control-giseca" style="width:160px;" onchange="this.form.submit()">
                <option value="">Todas las sucursales</option>
                @foreach ($sucursales as $s)
                    <option value="{{ $s->id }}" {{ (string) $sucursal_id === (string) $s->id ? 'selected' : '' }}>
                        {{ $s->nombre }} (Suc. {{ $s->codigo }})</option>
                @endforeach
            </select>
            <input type="text" name="q" class="form-control-giseca" style="width:200px;"
                placeholder="N°, cliente, factura..." value="{{ $q }}">
        </form>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn-giseca btn-outline"
                onclick="document.getElementById('modalSIAT').classList.add('abierto')"><i class="bi bi-bank"></i> Importar
                Libro SIAT</button>
            <button class="btn-giseca btn-outline" onclick="document.getElementById('modalCSV').classList.add('abierto')"><i
                    class="bi bi-file-earmark-arrow-up"></i> Importar CSV</button>
            <button class="btn-giseca btn-outline"
                onclick="document.getElementById('modalExcel').classList.add('abierto')"><i
                    class="bi bi-file-earmark-excel"></i> Importar Excel</button>
            <a class="btn-giseca btn-primario" href="{{ route('ventas.create') }}"><i class="bi bi-plus-lg"></i> Nueva
                Venta</a>
        </div>
    </div>

    @if ($errors->any())
        <div
            style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
            {{ $errors->first() }}</div>
    @endif

    <div class="card-giseca ventas-scroll" style="padding:0;">
        <table class="tabla-giseca">
            <thead>
                <tr>
                    <th>Comprobante venta</th>
                    <th>Origen / Sucursal</th>
                    <th>Modalidad</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th class="text-end">Total</th>
                    <th>Cobranza</th>
                    <th class="text-end">Débito Fiscal</th>
                    <th>Almacén</th>
                    <th>Comp. Ingreso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                    @php
                        $proximaCuota = $v->proxima_cuota;
                        $estadoCobranza = $v->estado_cobranza;
                        $estadoCobranzaLabel = [
                            'cobrada' => 'Cobrada',
                            'sin_plan' => 'Sin plan',
                            'vencida' => 'Vencida',
                            'por_vencer' => 'Por vencer',
                            'vigente' => 'Vigente',
                        ][$estadoCobranza] ?? ucfirst($estadoCobranza);
                        $estadoCobranzaClase = [
                            'cobrada' => 'estado-aprobada',
                            'vencida' => 'estado-rechazada',
                            'por_vencer' => 'estado-enviada',
                            'vigente' => 'estado-borrador',
                            'sin_plan' => 'estado-borrador',
                        ][$estadoCobranza] ?? 'estado-borrador';
                    @endphp
                    <tr class="{{ $v->estado === 'anulada' ? 'alerta' : '' }}">
                        <td>
                            <span class="codigo-chip">{{ $v->numero }}</span>
                            @if ($v->numero_factura_siat)
                                <div style="font-size:10.5px; color:var(--gc-gris-claro); margin-top:2px;">Fact.
                                    {{ $v->numero_factura_siat }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($v->origen_siat)
                                <span class="estado estado-enviada"><i class="bi bi-bank"></i> SIAT</span>
                            @else
                                <span
                                    class="estado {{ $v->tipo === 'con_factura' ? 'estado-aprobada' : 'estado-borrador' }}">{{ $v->tipo === 'con_factura' ? 'Con factura' : 'Sin factura' }}</span>
                            @endif
                            @if ($v->sucursal)
                                <div style="font-size:10px; color:var(--gc-gris); margin-top:3px;"><i
                                        class="bi bi-geo-alt"></i> {{ $v->sucursal->nombre }}</div>
                            @endif
                        </td>
                        <td><span
                                class="estado {{ $v->modalidad === 'credito' ? 'estado-rechazada' : 'estado-enviada' }}">{{ strtoupper($v->modalidad) }}</span>
                        </td>
                        <td>{{ $v->fecha->format('Y-m-d') }}</td>
                        <td>{{ $v->cliente_nombre }}</td>
                        <td class="text-end fw-bold">Bs {{ formatoMoneda($v->total) }}</td>
                        <td>
                            @if ($v->estado === 'anulada')
                                <span class="estado estado-borrador">—</span>
                            @else
                                <span class="estado {{ $estadoCobranzaClase }}">{{ $estadoCobranzaLabel }}</span>
                                <div class="ventas-muted">
                                    @if ($v->saldo > 0)
                                        Saldo Bs {{ formatoMoneda($v->saldo) }}
                                    @else
                                        Pagada
                                    @endif
                                </div>
                                @if ($proximaCuota)
                                    <div class="ventas-muted">Cuota #{{ $proximaCuota->numero }} · {{ $proximaCuota->fecha_vencimiento->format('Y-m-d') }}</div>
                                @endif
                            @endif
                        </td>
                        <td class="text-end">{{ $v->debito_fiscal ? 'Bs ' . formatoMoneda($v->debito_fiscal) : '—' }}</td>
                        <td>
                            @if ($v->origen_siat || $v->estado === 'anulada')
                                <span class="estado estado-borrador">—</span>
                            @else
                                <span class="estado {{ $v->entrega_estado === 'entregada' ? 'estado-aprobada' : ($v->entrega_estado === 'parcial' ? 'estado-enviada' : 'estado-borrador') }}">
                                    {{ ucfirst($v->entrega_estado) }}
                                </span>
                                <div style="font-size:10.5px; color:var(--gc-gris-claro); margin-top:3px;">{{ $v->porcentaje_entrega }}%</div>
                            @endif
                        </td>
                        <td><a href="{{ route('comprobantes.index', ['q' => $v->comprobante_numero]) }}"><span
                                    class="codigo-chip equivalente">{{ $v->comprobante_numero ?: '—' }}</span></a></td>
                        <td class="text-end" style="white-space:nowrap;">
                            @if ($v->estado === 'activa')
                                @can('admin')
                                    <form method="POST" action="{{ route('ventas.anular', $v) }}" style="display:inline;"
                                        data-confirm="¿Anular la venta {{ $v->numero }}? Se actualizarán las reservas y entregas de almacén."
                                        data-confirm-title="Anular venta" data-confirm-label="Anular venta" data-confirm-variant="peligro">
                                        @csrf<button type="submit" class="btn-giseca btn-outline btn-icon btn-sm"
                                            title="Anular"><i class="bi bi-x-circle"></i></button></form>
                                @endcan
                            @else
                                <span style="font-size:11px; color:var(--gc-gris-claro);">Anulada</span>
                            @endif
                            <button class="btn-giseca btn-outline btn-icon btn-sm" title="Ver detalle"
                                onclick='verDetalle(@json($v->loadMissing('detalles')))'><i class="bi bi-eye"></i></button>
                            @can('almacen')
                                @if ($v->estado === 'activa' && ! $v->origen_siat && $v->entrega_estado !== 'entregada')
                                    <a class="btn-giseca btn-outline btn-icon btn-sm" title="Preparar entrega"
                                        href="{{ route('almacen.show', $v) }}"><i class="bi bi-clipboard-check"
                                            style="color:var(--gc-primario);"></i></a>
                                @endif
                            @endcan
                            @can('comprobantes')
                                @if ($v->estado === 'activa' && $v->saldo > 0)
                                    <a class="btn-giseca btn-outline btn-icon btn-sm" title="Cobrar saldo"
                                        href="{{ route('cuentas.index', ['q' => $v->numero]) }}"><i class="bi bi-cash-coin"
                                            style="color:var(--gc-verde);"></i></a>
                                @endif
                            @endcan
                            @if ($v->tipo === 'con_factura' && $v->estado === 'activa')
                                @if ($v->facturaElectronica && $v->facturaElectronica->estado !== 'rechazada')
                                    <a class="btn-giseca btn-outline btn-icon btn-sm"
                                        title="Ver factura {{ $v->facturaElectronica->numero_factura }}"
                                        href="{{ route('facturas.show', $v->facturaElectronica) }}"><i
                                            class="bi bi-file-earmark-check" style="color:var(--gc-verde);"></i></a>
                                @else
                                    @can('facturas.emitir')
                                        <button class="btn-giseca btn-outline btn-icon btn-sm"
                                            title="Emitir factura"
                                            onclick="abrirModalEmitir('{{ route('facturas.emitir', $v) }}', '{{ $v->numero }}')"><i
                                                class="bi bi-file-earmark-plus"
                                                style="color:var(--gc-primario);"></i></button>
                                    @endcan
                                @endif
                            @endif
                            <a class="btn-giseca btn-outline btn-icon btn-sm" title="Editar"
                                href="{{ route('ventas.edit', $v) }}"><i class="bi bi-pencil"></i></a>
                            @can('admin')
                                <form method="POST" action="{{ route('ventas.destroy', $v) }}" style="display:inline;"
                                    data-confirm="¿Eliminar la venta {{ $v->numero }}? Se actualizará almacén y se borrará su comprobante."
                                    data-confirm-title="Eliminar venta" data-confirm-label="Eliminar" data-confirm-variant="peligro">
                                    @csrf @method('DELETE')<button type="submit"
                                        class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i
                                            class="bi bi-trash" style="color:var(--gc-rojo);"></i></button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i
                                class="bi bi-cash-coin"
                                style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin ventas
                            registradas todavía.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($ventas->hasPages())
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:12.5px; color:var(--gc-gris);">
            <span>Mostrando {{ $ventas->firstItem() }}–{{ $ventas->lastItem() }} de {{ $ventas->total() }}</span>
            <div style="display:flex; gap:8px;">
                @if ($ventas->onFirstPage())
                <span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">← Anterior</span>@else<a
                        class="btn-giseca btn-outline btn-sm" href="{{ $ventas->previousPageUrl() }}">← Anterior</a>
                @endif
                @if ($ventas->hasMorePages())
                    <a class="btn-giseca btn-outline btn-sm" href="{{ $ventas->nextPageUrl() }}">Siguiente
                    →</a>@else<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">Siguiente →</span>
                @endif
            </div>
        </div>
    @endif

    <div class="modal-giseca" id="modalDetalle">
        <div class="modal-box" style="max-width:900px; width:90%;">
            <h6 id="detTitulo">Detalle</h6>
            <div id="detCuerpo" style="font-size:13px; line-height:1.8;"></div>
            <div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-outline"
                    onclick="document.getElementById('modalDetalle').classList.remove('abierto')">Cerrar</button></div>
        </div>
    </div>

    <div class="modal-giseca" id="modalEmitir">
        <div class="modal-box">
            <h6>Emitir Factura</h6>
            <p style="font-size:12.5px; color:var(--gc-gris);" id="emitirTexto">Se firmará y enviará al SIN.</p>
            <form method="POST" action="" id="formEmitir">
                @csrf
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:14px;">
                    <input type="checkbox" name="contingencia" value="1"> Emitir en <strong>contingencia</strong>
                    (usa el CAFC configurado, tipo de emisión 2)
                </label>
                <div style="display:flex; gap:8px;">
                    <button class="btn-giseca btn-primario">Confirmar emisión</button>
                    <button type="button" class="btn-giseca btn-outline"
                        onclick="document.getElementById('modalEmitir').classList.remove('abierto')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-giseca" id="modalSIAT">
        <div class="modal-box">
            <h6><i class="bi bi-bank" style="color:var(--gc-primario);"></i> Importar Libro de Ventas SIAT</h6>
            <p style="font-size:12.5px; color:var(--gc-gris);">CSV del SIAT (Libro de Ventas IVA). No afecta el stock.
                Genera comprobante de Ingreso (salvo anuladas) y evita duplicados.</p>
            <form method="POST" action="{{ route('ventas.importar-siat') }}" enctype="multipart/form-data">@csrf<input
                    type="file" name="archivo" accept=".csv,.txt" class="form-control-giseca"
                    style="margin-bottom:12px;" required>
                <div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario"><i
                            class="bi bi-upload"></i> Importar</button><button type="button"
                        class="btn-giseca btn-outline"
                        onclick="document.getElementById('modalSIAT').classList.remove('abierto')">Cerrar</button></div>
            </form>
        </div>
    </div>

    <div class="modal-giseca" id="modalCSV">
        <div class="modal-box">
            <h6>Importar Ventas desde CSV</h6>
            <p style="font-size:12.5px; color:var(--gc-gris);">Columnas:
                <code>Numero,Fecha,Cliente,Tipo,Modalidad,Codigo,Descripcion,Cantidad,Precio,Descuento</code><br>El producto
                debe existir en Inventario. Si falta stock, el documento se rechaza.
            </p>
            <form method="POST" action="{{ route('ventas.importar-csv') }}" enctype="multipart/form-data">@csrf<input
                    type="file" name="archivo" accept=".csv,.txt" class="form-control-giseca"
                    style="margin-bottom:12px;" required>
                <div style="display:flex; gap:8px; margin-top:14px;"><button
                        class="btn-giseca btn-primario">Importar</button><button type="button"
                        class="btn-giseca btn-outline"
                        onclick="document.getElementById('modalCSV').classList.remove('abierto')">Cerrar</button></div>
            </form>
        </div>
    </div>

    <div class="modal-giseca" id="modalExcel">
        <div class="modal-box">
            <h6><i class="bi bi-file-earmark-excel" style="color:var(--gc-verde);"></i> Importar Ventas desde Excel</h6>
            <p style="font-size:12.5px; color:var(--gc-gris);">Mismas columnas que el CSV. Se procesa con SheetJS en tu
                navegador.</p>
            <button class="btn-giseca btn-outline btn-sm" onclick="descargarPlantilla()" style="margin-bottom:12px;"><i
                    class="bi bi-download"></i> Descargar plantilla (.xlsx)</button>
            <input type="file" id="archivoExcel" accept=".xlsx,.xls" class="form-control-giseca"
                style="margin-bottom:12px;">
            <div id="resultadoExcel"></div>
            <div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario" id="btnExcel"
                    onclick="procesarExcel()"><i class="bi bi-upload"></i> Importar</button><button
                    class="btn-giseca btn-outline"
                    onclick="document.getElementById('modalExcel').classList.remove('abierto')">Cerrar</button></div>
        </div>
    </div>
    <style>
        .tabla-detalle-venta {
            border-collapse: collapse;
        }

        .ventas-scroll {
            overflow-x: auto;
        }

        .ventas-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .ventas-kpis .kpi.rojo {
            border-top-color: var(--gc-rojo);
        }

        .ventas-scroll>.tabla-giseca {
            min-width: 1380px;
        }

        .ventas-muted {
            font-size: 10.5px;
            color: var(--gc-gris-claro);
            margin-top: 3px;
        }

        .tabla-detalle-venta th,
        .tabla-detalle-venta td {
            border-right: 1px solid var(--gc-borde);
            padding-left: 12px;
            padding-right: 12px;
        }

        .tabla-detalle-venta th:first-child,
        .tabla-detalle-venta td:first-child {
            border-left: 1px solid var(--gc-borde);
        }

        .tabla-detalle-venta th {
            border-top: 1px solid var(--gc-borde);
        }

        .tabla-detalle-venta td {
            border-bottom: 1px solid var(--gc-borde);
        }
    </style>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        function abrirModalEmitir(url, numero) {
            document.getElementById('formEmitir').action = url;
            document.getElementById('emitirTexto').textContent = 'Se firmará y enviará al SIN la venta ' + numero + '.';
            document.getElementById('modalEmitir').classList.add('abierto');
        }

        function verDetalle(v) {

            document.getElementById('detTitulo').textContent = 'Venta ' + v.numero;


            let html = `

    <div>
        <strong>Cliente:</strong> ${v.cliente_nombre}
    </div>

    <div>
        <strong>Fecha:</strong> ${v.fecha} · ${v.modalidad}
    </div>

    <hr style="border:none;border-top:1px solid var(--gc-borde);margin:10px 0;">


    <table class="tabla-giseca tabla-detalle-venta" style="width:100%; font-size:12px;">

        <thead>
            <tr>
                <th>Código interno</th>
                <th>Código</th>
                <th>Descripción</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
        </thead>


        <tbody>
    `;


            (v.detalles || []).forEach(d => {

                html += `

        <tr>

            <td>
                <span class="codigo-chip">
                    ${d.codigo_interno || '—'}
                </span>
            </td>


            <td>
                ${d.codigo_producto || '—'}
            </td>


            <td>
                ${d.descripcion_producto}
            </td>


            <td style="text-align:right;">
    ${Number(d.cantidad).toFixed(0)}
</td>


            <td style="text-align:right;">
                Bs ${Number(d.precio_unitario).toFixed(2)}
            </td>


            <td style="text-align:right;font-weight:bold;">
                Bs ${Number(d.subtotal).toFixed(2)}
            </td>

        </tr>

        `;

            });


            html += `

        </tbody>

    </table>


    <hr style="border:none;border-top:2px solid var(--gc-texto);margin:10px 0;">


    <div>
        <strong>Total: Bs ${Number(v.total).toFixed(2)}</strong>
    </div>


    <div>
        Comprobante: ${v.comprobante_numero || '—'}
    </div>

    `;


            document.getElementById('detCuerpo').innerHTML = html;

            document.getElementById('modalDetalle').classList.add('abierto');
        }

        function mostrarToast(mensaje, tipo) {
            let t = document.getElementById('toastGiseca');
            if (!t) {
                t = document.createElement('div');
                t.id = 'toastGiseca';
                t.className = 'toast-giseca';
                document.body.appendChild(t);
            }
            t.textContent = mensaje;
            t.className = 'toast-giseca mostrar ' + (tipo || '');
            setTimeout(() => t.classList.remove('mostrar'), 2800);
        }
        @if (session('exito'))
            mostrarToast(@json(session('exito')), 'exito');
        @endif
        @if (session('error'))
            mostrarToast(@json(session('error')), 'error');
        @endif

        function descargarPlantilla() {
            if (typeof XLSX === 'undefined') {
                mostrarToast('Sin conexión para cargar SheetJS', 'error');
                return;
            }
            const datos = [{
                Numero: 'NV-00001',
                Fecha: '{{ date('Y-m-d') }}',
                Cliente: 'Cliente Ejemplo',
                Tipo: 'SIN_FACTURA',
                Modalidad: 'CONTADO',
                Codigo: 'LF9009',
                Descripcion: 'Filtro de aceite',
                Cantidad: 2,
                Precio: 89.90,
                Descuento: 0
            }, ];
            const hoja = XLSX.utils.json_to_sheet(datos);
            const libro = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(libro, hoja, 'Ventas');
            XLSX.writeFile(libro, 'plantilla_ventas_giseca.xlsx');
        }
        async function procesarExcel() {
            if (typeof XLSX === 'undefined') {
                mostrarToast('Sin conexión para cargar SheetJS', 'error');
                return;
            }
            const archivo = document.getElementById('archivoExcel').files[0];
            if (!archivo) {
                mostrarToast('Selecciona un archivo Excel', 'error');
                return;
            }
            const btn = document.getElementById('btnExcel');
            btn.disabled = true;
            const lector = new FileReader();
            lector.onload = async (e) => {
                try {
                    const libro = XLSX.read(new Uint8Array(e.target.result), {
                        type: 'array'
                    });
                    const filas = XLSX.utils.sheet_to_json(libro.Sheets[libro.SheetNames[0]], {
                        defval: ''
                    });
                    const resp = await fetch("{{ route('ventas.importar-excel') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({
                            filas
                        }),
                    });
                    const r = await resp.json();
                    if (!resp.ok) throw new Error(r.message || 'Error en el servidor.');
                    document.getElementById('resultadoExcel').innerHTML =
                        '<div style="background:var(--gc-verde-suave);color:var(--gc-verde);padding:10px;border-radius:6px;font-size:13px;">' +
                        r.creados + ' venta(s), ' + r.duplicadas + ' duplicada(s).</div>' + (r.errores.length ?
                            '<div style="color:var(--gc-rojo);font-size:12px;margin-top:6px;">' + r.errores
                            .join('<br>') + '</div>' : '');
                    mostrarToast(r.message, 'exito');
                    setTimeout(() => window.location.reload(), 1500);
                } catch (err) {
                    document.getElementById('resultadoExcel').innerHTML =
                        '<div style="background:var(--gc-rojo-suave);color:var(--gc-rojo);padding:10px;border-radius:6px;font-size:12.5px;">' +
                        err.message + '</div>';
                } finally {
                    btn.disabled = false;
                }
            };
            lector.readAsArrayBuffer(archivo);
        }
    </script>
@endpush
