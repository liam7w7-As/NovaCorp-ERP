@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
    <div
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <form method="GET" action="{{ route('productos.index') }}"
            style="margin:0; flex-grow:1; max-width:380px; display:flex; gap:8px;">
            <input type="text" id="buscador" name="q" class="form-control-giseca" style="max-width:380px;"
                placeholder="Buscar por código, equivalente o descripción..." value="{{ $q }}">
        </form>
        <div style="display:flex; gap:8px;">
            <a class="btn-giseca btn-outline" href="{{ route('kardex.index') }}"><i class="bi bi-list-ul"></i> Kardex</a>
            <button class="btn-giseca btn-outline"
                onclick="document.getElementById('modalExcel').classList.add('abierto')"><i
                    class="bi bi-file-earmark-excel"></i> Importar Excel</button>
            <button class="btn-giseca btn-primario" onclick="abrirModal()"><i class="bi bi-plus-lg"></i> Nuevo
                Producto</button>
        </div>
    </div>

    @if ($errors->any())
        <div
            style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card-giseca" style="padding:0; overflow:hidden;">
        <div class="productos-scroll">
        <table class="tabla-giseca">
            <thead>
                <tr>

                    <th>
                        Código Interno
                    </th>

                    @include('comunes.sort-th', [
                        'campo' => 'codigo',
                        'etiqueta' => 'Código',
                        'sort' => $sort ?? 'descripcion',
                        'dir' => $dir ?? 'asc',
                    ])<th>Equivalente</th>@include('comunes.sort-th', [
                        'campo' => 'descripcion',
                        'etiqueta' => 'Descripción',
                        'sort' => $sort ?? 'descripcion',
                        'dir' => $dir ?? 'asc',
                    ])@include('comunes.sort-th', [
                        'campo' => 'marca',
                        'etiqueta' => 'Marca',
                        'sort' => $sort ?? 'descripcion',
                        'dir' => $dir ?? 'asc',
                    ])
                    <th>Unidad</th>
                    @include('comunes.sort-th', [
                        'campo' => 'costo',
                        'etiqueta' => 'Costo',
                        'clase' => 'text-end',
                        'sort' => $sort ?? 'descripcion',
                        'dir' => $dir ?? 'asc',
                    ])@include('comunes.sort-th', [
                        'campo' => 'precio',
                        'etiqueta' => 'P. Venta',
                        'clase' => 'text-end',
                        'sort' => $sort ?? 'descripcion',
                        'dir' => $dir ?? 'asc',
                    ])@include('comunes.sort-th', [
                        'campo' => 'stock',
                        'etiqueta' => 'Stock',
                        'clase' => 'text-end',
                        'sort' => $sort ?? 'descripcion',
                        'dir' => $dir ?? 'asc',
                    ])<th>Ficha</th><th></th>
                </tr>
            </thead>
            <tbody id="tbodyProductos">
                @forelse($productos as $p)
                    <tr class="{{ $p->en_alerta ? 'alerta' : '' }}"
                        data-search="{{ strtolower($p->codigo_interno . ' ' . $p->codigo . ' ' . ($p->equivalente ?? '') . ' ' . $p->descripcion . ' ' . ($p->marca ?? '')) }}">
                        <td>
                            <span class="codigo-chip" style="background:#e8f5e9;color:#198754;">
                                {{ $p->codigo_interno ?? '---' }}
                            </span>
                        </td>
                        <td><span class="codigo-chip">{{ $p->codigo }}</span></td>
                        <td>{!! $p->equivalente ? '<span class="codigo-chip equivalente">' . e($p->equivalente) . '</span>' : '—' !!}</td>
                        <td>{{ $p->descripcion }}</td>
                        <td>{{ $p->marca ?: '—' }}</td>
                        <td>{{ $p->unidad ?: 'PZA' }}</td>
                        <td class="text-end">{{ formatoMoneda($p->costo) }}</td>
                        <td class="text-end">{{ formatoMoneda($p->precio) }}</td>
                        <td class="text-end"
                            style="{{ $p->en_alerta ? 'color:var(--gc-rojo); font-weight:700;' : '' }}">
                            {{ rtrim(rtrim(number_format((float) $p->stock, 2, '.', ''), '0'), '.') }}
                            @if ((float) $p->stock_reservado > 0)
                                <div class="producto-stock-detalle">
                                    Disp. {{ formatoMoneda($p->stock_disponible) }} · Res. {{ formatoMoneda($p->stock_reservado) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="producto-adjuntos-tabla">
                                @if ($p->ficha_tecnica_url)
                                    <a class="producto-adjunto-link" href="{{ $p->ficha_tecnica_url }}" target="_blank"
                                        rel="noopener" title="{{ $p->ficha_tecnica_nombre ?: 'Ficha técnica' }}"><i
                                            class="bi bi-file-earmark-pdf"></i> Ficha</a>
                                @else
                                    <span class="producto-adjunto-vacio">Sin ficha</span>
                                @endif
                                @if (count($p->imagenes_producto))
                                    <a class="producto-adjunto-link" href="{{ $p->imagenes_producto[0]['url'] }}"
                                        target="_blank" rel="noopener"><i class="bi bi-images"></i>
                                        {{ count($p->imagenes_producto) }}</a>
                                @endif
                            </div>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            <button class="btn-giseca btn-outline btn-icon btn-sm" title="Editar"
                                onclick='editarProducto(@json($p))'><i class="bi bi-pencil"></i></button>
                            <button class="btn-giseca btn-outline btn-icon btn-sm" title="Código de barras"
                                onclick="verBarra({{ $p->id }})"><i class="bi bi-upc-scan"></i></button>
                            @can('admin')
                                <form method="POST" action="{{ route('productos.destroy', $p) }}" style="display:inline;"
                                    onsubmit="return confirm('¿Eliminar el producto {{ $p->codigo }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i
                                            class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i
                                class="bi bi-box-seam"
                                style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin productos
                            registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    <div id="sinResultados" style="display:none; text-align:center; color:var(--gc-gris-claro); padding:40px;"><i
            class="bi bi-box-seam" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin productos
        que coincidan.</div>

    @if ($productos->hasPages())
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:12.5px; color:var(--gc-gris);">
            <span>Mostrando {{ $productos->firstItem() }}–{{ $productos->lastItem() }} de
                {{ $productos->total() }}</span>
            <div style="display:flex; gap:8px;">
                @if ($productos->onFirstPage())
                    <span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">← Anterior</span>
                @else
                    <a class="btn-giseca btn-outline btn-sm" href="{{ $productos->previousPageUrl() }}">← Anterior</a>
                @endif
                @if ($productos->hasMorePages())
                    <a class="btn-giseca btn-outline btn-sm" href="{{ $productos->nextPageUrl() }}">Siguiente →</a>
                @else
                    <span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">Siguiente →</span>
                @endif
            </div>
        </div>
    @else
        <div style="margin-top:10px; font-size:12px; color:var(--gc-gris-claro);">{{ $productos->total() }} producto(s) en
            total.</div>
    @endif

    <!-- Modal Nuevo/Editar Producto -->
    <div class="modal-giseca" id="modalProducto">
        <div class="modal-box">
            <h6 id="modalTitulo">Nuevo Producto</h6>
            <form id="formProducto" method="POST" action="{{ route('productos.store') }}" enctype="multipart/form-data">
                @csrf
                <div id="methodContainer"></div>
                <div>
                    <label class="form-label-giseca">
                        Código interno
                    </label>

                    <input class="form-control-giseca" id="f_codigoInterno" name="codigo_interno" readonly
                        placeholder="Se genera automáticamente">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div><label class="form-label-giseca">Código *</label><input class="form-control-giseca"
                                id="f_codigo" name="codigo" required></div>
                        <div><label class="form-label-giseca">Equivalente</label><input class="form-control-giseca"
                                id="f_equivalente" name="equivalente"></div>
                    </div>
                    <div style="margin-top:10px;"><label class="form-label-giseca">Descripción *</label><input
                            class="form-control-giseca" id="f_descripcion" name="descripcion" required></div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
                        <div><label class="form-label-giseca">Marca</label><input class="form-control-giseca"
                                id="f_marca" name="marca"></div>
                        <div><label class="form-label-giseca">Unidad</label><input class="form-control-giseca"
                                id="f_unidad" name="unidad" value="PZA"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
                        <div style="position:relative;"><label class="form-label-giseca">Código SIN <i
                                    class="bi bi-info-circle"
                                    title="Escribe para buscar en el catálogo oficial SIN"></i></label><input
                                class="form-control-giseca" id="f_codigoSin" name="codigo_sin"
                                placeholder="Buscar ej: aceite, 32110, filtro..." autocomplete="off">
                            <div id="resSin"
                                style="position:absolute; background:var(--gc-superficie); border:1px solid var(--gc-borde); border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,.12); width:100%; max-height:200px; overflow-y:auto; z-index:60; margin-top:4px;">
                            </div>
                        </div>
                        <div style="position:relative;"><label class="form-label-giseca">Unidad SIN <i
                                    class="bi bi-info-circle"
                                    title="Escribe para buscar unidad oficial (ej: 58 unidad, 6 caja, 22 kg)"></i></label><input
                                class="form-control-giseca" id="f_unidadSin" name="unidad_sin"
                                placeholder="Buscar ej: 58, unidad, caja..." autocomplete="off">
                            <div id="resUnidadSin"
                                style="position:absolute; background:var(--gc-superficie); border:1px solid var(--gc-borde); border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,.12); width:100%; max-height:180px; overflow-y:auto; z-index:60; margin-top:4px;">
                            </div>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; margin-top:10px;">
                        <div><label class="form-label-giseca">Costo</label><input type="number" step="0.01"
                                min="0" class="form-control-giseca" id="f_costo" name="costo"
                                value="0"></div>
                        <div><label class="form-label-giseca">P. Venta</label><input type="number" step="0.01"
                                min="0" class="form-control-giseca" id="f_precio" name="precio"
                                value="0"></div>
                        <div><label class="form-label-giseca">Stock</label><input type="number" step="0.01"
                                class="form-control-giseca" id="f_stock" name="stock" value="0"></div>
                        <div><label class="form-label-giseca">Stock mín.</label><input type="number" step="0.01"
                                min="0" class="form-control-giseca" id="f_stockMin" name="stock_min"
                                value="0"></div>
                    </div>
                    <div class="producto-adjuntos-form">
                        <div>
                            <label class="form-label-giseca">Ficha técnica (PDF)</label>
                            <input type="file" class="form-control-giseca" id="f_fichaTecnica"
                                name="ficha_tecnica" accept="application/pdf">
                            <div id="fichaTecnicaActual" class="producto-adjunto-actual"></div>
                        </div>
                        <div>
                            <label class="form-label-giseca">Imágenes del producto</label>
                            <input type="file" class="form-control-giseca" id="f_imagenes" name="imagenes[]"
                                accept="image/jpeg,image/png,image/webp" multiple>
                            <div id="imagenesActuales" class="producto-imagenes-actuales"></div>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; margin-top:18px;">
                        <button type="submit" class="btn-giseca btn-primario">Guardar</button>
                        <button type="button" class="btn-giseca btn-outline" onclick="cerrarModal()">Cancelar</button>
                    </div>
            </form>
        </div>
    </div>
    </div>

    <!-- Modal Importar Excel -->
    <div class="modal-giseca" id="modalExcel">
        <div class="modal-box">
            <h6><i class="bi bi-file-earmark-excel" style="color:var(--gc-verde);"></i> Importar Inventario desde Excel
            </h6>
            <p style="font-size:12.5px; color:var(--gc-gris);">
                Columnas esperadas (primera fila del Excel): <code>Codigo, Equivalente, Marca, Descripcion, Unidad, Costo,
                    PrecioVenta, Stock, StockMinimo</code>.
                Si el código ya existe, se <strong>actualiza</strong> el producto y el stock del Excel se
                <strong>suma</strong> al actual (pensado para reposición).
                Si no existe, se <strong>crea</strong> automáticamente.
            </p>
            <button class="btn-giseca btn-outline btn-sm" onclick="descargarPlantillaExcel()"
                style="margin-bottom:12px;">
                <i class="bi bi-download"></i> Descargar plantilla de ejemplo (.xlsx)
            </button>
            <input type="file" id="archivoExcel" accept=".xlsx,.xls" class="form-control-giseca"
                style="margin-bottom:12px;">
            <div id="resultadoImportacionExcel"></div>
            <div style="display:flex; gap:8px; margin-top:14px;">
                <button class="btn-giseca btn-primario" id="btnImportarExcel" onclick="procesarExcelInventario()"><i
                        class="bi bi-upload"></i> Importar</button>
                <button class="btn-giseca btn-outline"
                    onclick="document.getElementById('modalExcel').classList.remove('abierto')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal Código de barras -->
    <div class="modal-giseca" id="modalBarra">
        <div class="modal-box" style="text-align:center;">
            <h6 id="barraTitulo">Código de barras</h6>
            <div id="barraCodigo" style="font-size:12px; color:var(--gc-gris); margin-bottom:10px;"></div>
            <div id="barraImg"
                style="background:#fff; padding:14px; border:1px solid var(--gc-borde); border-radius:6px; overflow-x:auto;">
            </div>
            <div style="display:flex; gap:8px; margin-top:14px; justify-content:center;">
                <button class="btn-giseca btn-oscuro" onclick="window.print()"><i class="bi bi-printer"></i>
                    Imprimir</button>
                <button class="btn-giseca btn-outline"
                    onclick="document.getElementById('modalBarra').classList.remove('abierto')">Cerrar</button>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .productos-scroll{overflow-x:auto}.productos-scroll .tabla-giseca{min-width:1120px}.producto-stock-detalle{font-size:10.5px;color:var(--gc-gris);font-weight:600;white-space:nowrap}.producto-adjuntos-tabla{display:flex;flex-direction:column;align-items:flex-start;gap:4px;min-width:82px}.producto-adjunto-link{display:inline-flex;align-items:center;gap:4px;color:var(--gc-info);font-size:11px;font-weight:700;text-decoration:none}.producto-adjunto-link i{font-size:13px}.producto-adjunto-vacio{font-size:10.5px;color:var(--gc-gris-claro)}.producto-adjuntos-form{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;border:1px solid var(--gc-borde);border-radius:7px;padding:12px;background:var(--gc-fondo)}.producto-adjunto-actual,.producto-imagenes-actuales{display:grid;gap:6px;margin-top:8px}.producto-archivo-actual{display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid var(--gc-borde);border-radius:6px;padding:8px;background:var(--gc-superficie);font-size:11.5px}.producto-archivo-actual a{color:var(--gc-info);font-weight:700;text-decoration:none;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.producto-archivo-actual label{display:flex;align-items:center;gap:5px;color:var(--gc-rojo);font-size:10.5px;white-space:nowrap}.producto-imagen-chip{display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:8px;border:1px solid var(--gc-borde);border-radius:6px;padding:6px;background:var(--gc-superficie)}.producto-imagen-chip img{width:34px;height:34px;object-fit:cover;border-radius:5px;background:var(--gc-fondo)}.producto-imagen-chip a{color:var(--gc-texto);font-size:11px;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.producto-imagen-chip label{display:flex;align-items:center;gap:5px;color:var(--gc-rojo);font-size:10.5px;white-space:nowrap}@media(max-width:760px){.producto-adjuntos-form{grid-template-columns:1fr}.modal-box{width:min(96vw,680px)}}
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        (function() {
            const buscador = document.getElementById('buscador');
            let timer = null;
            if (buscador) {
                // Filtro en vivo sobre las filas ya cargadas + búsqueda de servidor con debounce
                buscador.addEventListener('input', function() {
                    const t = this.value.toLowerCase();
                    let visibles = 0;
                    document.querySelectorAll('#tbodyProductos tr[data-search]').forEach(tr => {
                        const ok = !t || tr.getAttribute('data-search').includes(t);
                        tr.style.display = ok ? '' : 'none';
                        if (ok) visibles++;
                    });
                    document.getElementById('sinResultados').style.display = visibles ? 'none' : 'block';
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        const url = new URL(window.location.href);
                        if (this.value) url.searchParams.set('q', this.value);
                        else url.searchParams.delete('q');
                        url.searchParams.delete('page');
                        window.history.replaceState({}, '', url);
                    }, 600);
                });
                // Enter → búsqueda en servidor
                buscador.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.form.submit();
                    }
                });
            }
        })();

        function abrirModal() {
            document.getElementById('modalTitulo').textContent = 'Nuevo Producto';
            document.getElementById('formProducto').action = "{{ route('productos.store') }}";
            document.getElementById('methodContainer').innerHTML = '';
            ['f_codigo', 'f_equivalente', 'f_descripcion', 'f_marca', 'f_codigoSin', 'f_unidadSin'].forEach(id => document
                .getElementById(id).value = '');
            document.getElementById('f_unidad').value = 'PZA';
            ['f_costo', 'f_precio', 'f_stock', 'f_stockMin'].forEach(id => document.getElementById(id).value = 0);
            document.getElementById('f_fichaTecnica').value = '';
            document.getElementById('f_imagenes').value = '';
            pintarAdjuntosProducto({});
            document.getElementById('modalProducto').classList.add('abierto');
        }

        function editarProducto(p) {
            document.getElementById('modalTitulo').textContent = 'Editar Producto';
            document.getElementById('formProducto').action = "/productos/" + p.id;
            document.getElementById('methodContainer').innerHTML = '@method('PUT')';
            document.getElementById('f_codigoInterno').value = p.codigo_interno || '';
            document.getElementById('f_codigo').value = p.codigo || '';
            document.getElementById('f_equivalente').value = p.equivalente || '';
            document.getElementById('f_descripcion').value = p.descripcion || '';
            document.getElementById('f_marca').value = p.marca || '';
            document.getElementById('f_unidad').value = p.unidad || 'PZA';
            document.getElementById('f_codigoSin').value = p.codigo_sin || '';
            document.getElementById('f_unidadSin').value = p.unidad_sin || '';
            document.getElementById('f_costo').value = p.costo ?? 0;
            document.getElementById('f_precio').value = p.precio ?? 0;
            document.getElementById('f_stock').value = p.stock ?? 0;
            document.getElementById('f_stockMin').value = p.stock_min ?? 0;
            document.getElementById('f_fichaTecnica').value = '';
            document.getElementById('f_imagenes').value = '';
            pintarAdjuntosProducto(p);
            document.getElementById('modalProducto').classList.add('abierto');
        }

        function cerrarModal() {
            document.getElementById('modalProducto').classList.remove('abierto');
        }

        function pintarAdjuntosProducto(p) {
            const ficha = document.getElementById('fichaTecnicaActual');
            const imagenes = document.getElementById('imagenesActuales');
            const fichaUrl = p.ficha_tecnica_url || '';
            const fichaNombre = p.ficha_tecnica_nombre || 'Ficha técnica';
            ficha.innerHTML = fichaUrl ? `
                <div class="producto-archivo-actual">
                    <a href="${fichaUrl}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> ${escaparHtml(fichaNombre)}</a>
                    <label><input type="checkbox" name="quitar_ficha_tecnica" value="1"> Quitar</label>
                </div>` : '<span class="producto-adjunto-vacio">Aún no tiene ficha técnica cargada.</span>';

            const lista = Array.isArray(p.imagenes_producto) ? p.imagenes_producto : [];
            imagenes.innerHTML = lista.length ? lista.map((img, indice) => `
                <div class="producto-imagen-chip">
                    <img src="${img.url}" alt="${escaparHtml(img.nombre || 'Imagen del producto')}" loading="lazy">
                    <a href="${img.url}" target="_blank" rel="noopener">${escaparHtml(img.nombre || 'Imagen del producto')}</a>
                    <label><input type="checkbox" name="quitar_imagenes[]" value="${indice}"> Quitar</label>
                </div>`).join('') : '<span class="producto-adjunto-vacio">Sin imágenes asociadas.</span>';
        }

        function escaparHtml(valor) {
            const nodo = document.createElement('div');
            nodo.textContent = valor || '';
            return nodo.innerHTML;
        }

        (function() {
            const inp = document.getElementById('f_codigoSin');
            const box = document.getElementById('resSin');
            const inpUnidad = document.getElementById('f_unidadSin');
            const boxUnidad = document.getElementById('resUnidadSin');
            if (!inp || !box) return;
            let t = null;
            let tU = null;

            inp.addEventListener('input', function() {
                clearTimeout(t);
                const q = this.value.trim();
                if (q.length < 2) {
                    box.innerHTML = '';
                    return;
                }
                t = setTimeout(async () => {
                    try {
                        const r = await fetch("{{ route('catalogos.productos') }}?q=" +
                            encodeURIComponent(q), {
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });
                        const lista = await r.json();
                        box.innerHTML = lista.map(p =>
                                `<div class="item" data-cod="${p.codigo}" style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--gc-borde); font-size:12.5px;"><strong>${p.codigo}</strong> — ${p.descripcion}</div>`
                            ).join('') ||
                            '<div style="padding:8px 12px; font-size:12px; color:var(--gc-gris);">Sin resultados. Puedes escribir el código manual.</div>';
                        box.querySelectorAll('.item[data-cod]').forEach(el => {
                            el.addEventListener('click', () => {
                                inp.value = el.dataset.cod;
                                box.innerHTML = '';
                                if (inpUnidad && !inpUnidad.value) inpUnidad.value =
                                    '58';
                            });
                        });
                    } catch (e) {
                        box.innerHTML = '';
                    }
                }, 220);
            });

            if (inpUnidad && boxUnidad) {
                inpUnidad.addEventListener('input', function() {
                    clearTimeout(tU);
                    const q = this.value.trim();
                    tU = setTimeout(async () => {
                        try {
                            const r = await fetch("{{ route('catalogos.unidades') }}?q=" +
                                encodeURIComponent(q), {
                                    headers: {
                                        'Accept': 'application/json'
                                    }
                                });
                            const lista = await r.json();
                            boxUnidad.innerHTML = lista.map(u =>
                                    `<div class="item-u" data-cod="${u.codigo}" style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--gc-borde); font-size:12.5px;"><strong>${u.codigo}</strong> — ${u.descripcion}</div>`
                                ).join('') ||
                                '<div style="padding:8px 12px; font-size:12px; color:var(--gc-gris);">Sin unidades.</div>';
                            boxUnidad.querySelectorAll('.item-u[data-cod]').forEach(el => {
                                el.addEventListener('click', () => {
                                    inpUnidad.value = el.dataset.cod;
                                    boxUnidad.innerHTML = '';
                                });
                            });
                        } catch (e) {
                            boxUnidad.innerHTML = '';
                        }
                    }, 200);
                });
            }

            document.addEventListener('click', e => {
                if (inp && box && !inp.contains(e.target) && !box.contains(e.target)) box.innerHTML = '';
                if (inpUnidad && boxUnidad && !inpUnidad.contains(e.target) && !boxUnidad.contains(e.target))
                    boxUnidad.innerHTML = '';
            });
        })();

        function verBarra(id) {
            fetch('/productos/' + id + '/codigo-barra', {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(d => {
                    document.getElementById('barraTitulo').textContent = d.codigo;
                    document.getElementById('barraCodigo').textContent = d.descripcion;
                    document.getElementById('barraImg').innerHTML = d.html;
                    document.getElementById('modalBarra').classList.add('abierto');
                })
                .catch(() => mostrarToast('No se pudo generar el código', 'error'));
        }

        function mostrarToast(mensaje, tipo) {
            let toast = document.getElementById('toastGiseca');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toastGiseca';
                toast.className = 'toast-giseca';
                document.body.appendChild(toast);
            }
            toast.textContent = mensaje;
            toast.className = 'toast-giseca mostrar ' + (tipo || '');
            setTimeout(() => toast.classList.remove('mostrar'), 2800);
        }

        @if (session('exito'))
            mostrarToast(@json(session('exito')), 'exito');
        @endif

        @if (session('error'))
            mostrarToast(@json(session('error')), 'error');
        @endif

        // ---- Importación Excel (SheetJS → JSON → backend) ----
        function libreriaExcelDisponible() {
            if (typeof XLSX === 'undefined') {
                document.getElementById('resultadoImportacionExcel').innerHTML =
                    `<div
        style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px; border-radius:6px; font-size:12.5px;">
        <i class="bi bi-wifi-off"></i> No se pudo cargar el lector de Excel (¿sin conexión a internet?). Vuelve a
        intentarlo cuando tengas internet, o usa el formulario "Nuevo Producto" para cargar manualmente.
    </div>`;
                return false;
            }
            return true;
        }

        async function procesarExcelInventario() {
            if (!libreriaExcelDisponible()) return;
            const archivo = document.getElementById('archivoExcel').files[0];
            if (!archivo) {
                mostrarToast('Selecciona un archivo Excel', 'error');
                return;
            }
            const btn = document.getElementById('btnImportarExcel');
            btn.disabled = true;

            const lector = new FileReader();
            lector.onload = async (e) => {
                try {
                    const datos = new Uint8Array(e.target.result);
                    const libro = XLSX.read(datos, {
                        type: 'array'
                    });
                    const primeraHoja = libro.Sheets[libro.SheetNames[0]];
                    const filas = XLSX.utils.sheet_to_json(primeraHoja, {
                        defval: ''
                    });
                    if (!filas.length) throw new Error('El archivo no tiene filas de datos.');

                    const resp = await fetch("{{ route('productos.importar') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        },
                        body: JSON.stringify({
                            productos: filas
                        }),
                    });
                    const resultado = await resp.json();
                    if (!resp.ok) throw new Error(resultado.message || 'Error en el servidor.');

                    document.getElementById('resultadoImportacionExcel').innerHTML = `
    <div style="background:var(--gc-verde-suave); color:var(--gc-verde); padding:10px; border-radius:6px; font-size:13px;">
        ${resultado.creados} producto(s) nuevo(s), ${resultado.actualizados} actualizado(s).
    </div>
    ${(resultado.errores && resultado.errores.length) ? `<div
                style="color:var(--gc-rojo); font-size:12px; margin-top:6px;">${resultado.errores.join('<br>')}</div>` : ''}
    `;
                    mostrarToast(
                        `Importación completa: ${resultado.creados} nuevos, ${resultado.actualizados} actualizados`,
                        'exito');
                    setTimeout(() => window.location.reload(), 1200);
                } catch (err) {
                    document.getElementById('resultadoImportacionExcel').innerHTML =
                        `<div
        style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px; border-radius:6px; font-size:12.5px;">
        Error leyendo el archivo: ${err.message}. Verifica que sea un .xlsx válido con los encabezados correctos.
    </div>`;
                } finally {
                    btn.disabled = false;
                }
            };
            lector.readAsArrayBuffer(archivo);
        }

        function descargarPlantillaExcel() {
            if (!libreriaExcelDisponible()) return;
            const datosEjemplo = [{
                    Codigo: 'LF9009',
                    Equivalente: 'P550949',
                    Marca: 'Fleetguard',
                    Descripcion: 'Filtro de aceite Fleetguard para motor Cummins',
                    Unidad: 'PZA',
                    Costo: 45,
                    PrecioVenta: 89.90,
                    Stock: 20,
                    StockMinimo: 10
                },
                {
                    Codigo: 'NUEVO-001',
                    Equivalente: '',
                    Marca: 'Tu marca',
                    Descripcion: 'Descripción del producto',
                    Unidad: 'PZA',
                    Costo: 0,
                    PrecioVenta: 0,
                    Stock: 0,
                    StockMinimo: 0
                },
            ];
            const hoja = XLSX.utils.json_to_sheet(datosEjemplo);
            const libro = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(libro, hoja, 'Inventario');
            XLSX.writeFile(libro, 'plantilla_inventario_giseca.xlsx');
        }
    </script>
@endpush
