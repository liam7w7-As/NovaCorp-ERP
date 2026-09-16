@extends('layouts.app')

@section('title', 'Compras')

@section('content')
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px;">
  <div class="kpi naranja"><div class="label"><i class="bi bi-cart-plus-fill"></i> Total comprado</div><div class="value">Bs {{ formatoMoneda($kpis['total']) }}</div></div>
  <div class="kpi azul"><div class="label"><i class="bi bi-receipt-cutoff"></i> Crédito fiscal (SIAT)</div><div class="value">Bs {{ formatoMoneda($kpis['credito_fiscal']) }}</div></div>
  <div class="kpi verde"><div class="label"><i class="bi bi-file-earmark-check"></i> Compras con factura</div><div class="value">{{ $kpis['con_factura'] }}</div></div>
  <div class="kpi gris"><div class="label"><i class="bi bi-file-earmark"></i> Compras sin factura</div><div class="value">{{ $kpis['sin_factura'] }}</div></div>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('compras.index') }}" style="margin:0; display:flex; gap:8px; flex-wrap:wrap;">
    <select name="tipo" class="form-control-giseca" style="width:180px;" onchange="this.form.submit()">
      <option value="">Todos los tipos</option>
      <option value="con_factura" {{ $tipo === 'con_factura' ? 'selected' : '' }}>Con factura</option>
      <option value="sin_factura" {{ $tipo === 'sin_factura' ? 'selected' : '' }}>Sin factura</option>
    </select>
    <input type="text" name="q" class="form-control-giseca" style="width:240px;" placeholder="N°, proveedor, factura..." value="{{ $q }}">
  </form>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <button class="btn-giseca btn-outline" onclick="document.getElementById('modalSIAT').classList.add('abierto')"><i class="bi bi-bank"></i> Importar Libro SIAT</button>
    <button class="btn-giseca btn-outline" onclick="document.getElementById('modalCSV').classList.add('abierto')"><i class="bi bi-file-earmark-arrow-up"></i> Importar CSV</button>
    <button class="btn-giseca btn-outline" onclick="document.getElementById('modalExcel').classList.add('abierto')"><i class="bi bi-file-earmark-excel"></i> Importar Excel</button>
    <a class="btn-giseca btn-primario" href="{{ route('compras.create') }}"><i class="bi bi-plus-lg"></i> Nueva Compra</a>
  </div>
</div>

@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <table class="tabla-giseca">
    <thead><tr><th>Documento</th><th>Origen</th><th>Fecha</th><th>Proveedor</th><th class="text-end">Total</th><th class="text-end">Crédito Fiscal</th><th>Comprobante</th><th></th></tr></thead>
    <tbody>
      @forelse($compras as $c)
        <tr>
          <td><span class="codigo-chip">{{ $c->numero }}</span>@if($c->numero_factura_siat)<div style="font-size:10.5px; color:var(--gc-gris-claro); margin-top:2px;">Fact. {{ $c->numero_factura_siat }}</div>@endif</td>
          <td>@if($c->origen_siat)<span class="estado estado-enviada"><i class="bi bi-bank"></i> SIAT</span>@else<span class="estado {{ $c->tipo === 'con_factura' ? 'estado-aprobada' : 'estado-borrador' }}">{{ $c->tipo === 'con_factura' ? 'Con factura' : 'Sin factura' }}</span>@endif</td>
          <td>{{ $c->fecha->format('Y-m-d') }}</td>
          <td>{{ $c->proveedor_nombre }}</td>
          <td class="text-end fw-bold">Bs {{ formatoMoneda($c->total) }}</td>
          <td class="text-end">{{ $c->credito_fiscal ? 'Bs '.formatoMoneda($c->credito_fiscal) : '—' }}</td>
          <td><a href="{{ route('comprobantes.index', ['q' => $c->comprobante_numero]) }}"><span class="codigo-chip equivalente">{{ $c->comprobante_numero ?: '—' }}</span></a></td>
          <td class="text-end" style="white-space:nowrap;">
            <button class="btn-giseca btn-outline btn-icon btn-sm" title="Ver detalle" onclick='verDetalle(@json($c->load("detalles")))'><i class="bi bi-eye"></i></button>
            <a class="btn-giseca btn-outline btn-icon btn-sm" title="Editar" href="{{ route('compras.edit', $c) }}"><i class="bi bi-pencil"></i></a>
            @can('admin')<form method="POST" action="{{ route('compras.destroy', $c) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar la compra {{ $c->numero }}? Se revertirá el stock y se borrará su comprobante.')">@csrf @method('DELETE')<button type="submit" class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i class="bi bi-trash" style="color:var(--gc-rojo);"></i></button></form>@endcan
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center; color:var(--gc-gris-claro); padding:40px;"><i class="bi bi-cart-x" style="font-size:30px; display:block; margin-bottom:8px; opacity:.5;"></i>Sin compras registradas todavía.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($compras->hasPages())
  <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:12.5px; color:var(--gc-gris);">
    <span>Mostrando {{ $compras->firstItem() }}–{{ $compras->lastItem() }} de {{ $compras->total() }}</span>
    <div style="display:flex; gap:8px;">
      @if($compras->onFirstPage())<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">← Anterior</span>@else<a class="btn-giseca btn-outline btn-sm" href="{{ $compras->previousPageUrl() }}">← Anterior</a>@endif
      @if($compras->hasMorePages())<a class="btn-giseca btn-outline btn-sm" href="{{ $compras->nextPageUrl() }}">Siguiente →</a>@else<span class="btn-giseca btn-outline btn-sm" style="opacity:.5;">Siguiente →</span>@endif
    </div>
  </div>
@endif

<!-- Modal: Detalle -->
<div class="modal-giseca" id="modalDetalle"><div class="modal-box"><h6 id="detTitulo">Detalle</h6><div id="detCuerpo" style="font-size:13px; line-height:1.8;"></div><div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-outline" onclick="document.getElementById('modalDetalle').classList.remove('abierto')">Cerrar</button></div></div></div>

<!-- Modal: Importar SIAT -->
<div class="modal-giseca" id="modalSIAT">
  <div class="modal-box">
    <h6><i class="bi bi-bank" style="color:var(--gc-primario);"></i> Importar Libro de Compras SIAT</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Sube el CSV del SIAT (Libro de Compras IVA). No afecta el stock — solo registro fiscal y comprobante de Egreso. Las facturas repetidas (código de autorización) se omiten.</p>
    <form method="POST" action="{{ route('compras.importar-siat') }}" enctype="multipart/form-data">@csrf<input type="file" name="archivo" accept=".csv,.txt" class="form-control-giseca" style="margin-bottom:12px;" required><div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario"><i class="bi bi-upload"></i> Importar</button><button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalSIAT').classList.remove('abierto')">Cerrar</button></div></form>
  </div>
</div>

<!-- Modal: Importar CSV genérico -->
<div class="modal-giseca" id="modalCSV">
  <div class="modal-box">
    <h6>Importar Compras desde CSV</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Columnas: <code>Numero,Fecha,Proveedor,Tipo,Codigo,Descripcion,Cantidad,Costo,Descuento</code><br>Mismo <strong>Numero</strong> en varias filas = un documento. <strong>Tipo</strong> = CON_FACTURA o SIN_FACTURA. Si el código no existe, se crea.</p>
    <form method="POST" action="{{ route('compras.importar-csv') }}" enctype="multipart/form-data">@csrf<input type="file" name="archivo" accept=".csv,.txt" class="form-control-giseca" style="margin-bottom:12px;" required><div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario">Importar</button><button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalCSV').classList.remove('abierto')">Cerrar</button></div></form>
  </div>
</div>

<!-- Modal: Importar Excel -->
<div class="modal-giseca" id="modalExcel">
  <div class="modal-box">
    <h6><i class="bi bi-file-earmark-excel" style="color:var(--gc-verde);"></i> Importar Compras desde Excel</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Mismas columnas que el CSV. Se procesa en tu navegador con SheetJS y se envía al servidor.</p>
    <button class="btn-giseca btn-outline btn-sm" onclick="descargarPlantilla()" style="margin-bottom:12px;"><i class="bi bi-download"></i> Descargar plantilla (.xlsx)</button>
    <input type="file" id="archivoExcel" accept=".xlsx,.xls" class="form-control-giseca" style="margin-bottom:12px;">
    <div id="resultadoExcel"></div>
    <div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario" id="btnExcel" onclick="procesarExcel()"><i class="bi bi-upload"></i> Importar</button><button class="btn-giseca btn-outline" onclick="document.getElementById('modalExcel').classList.remove('abierto')">Cerrar</button></div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function verDetalle(c) {
  document.getElementById('detTitulo').textContent = 'Compra ' + c.numero;
  let html = '<div><strong>Proveedor:</strong> ' + c.proveedor_nombre + '</div><div><strong>Fecha:</strong> ' + c.fecha + '</div><hr style="border:none;border-top:1px solid var(--gc-borde);margin:8px 0;">';
  if (c.origen_siat) {
    html += '<div>NIT: ' + (c.nit_proveedor || '—') + '</div><div>Factura SIAT: ' + (c.numero_factura_siat || '—') + '</div><div>Base CF: Bs ' + (c.base_cf || 0) + '</div><div>Crédito fiscal: Bs ' + (c.credito_fiscal || 0) + '</div><div>Comprobante: ' + (c.comprobante_numero || '—') + '</div><div style="color:var(--gc-gris-claro);font-size:12px;">Sin detalle de productos (SIAT no lo incluye).</div>';
  } else {
    (c.detalles || []).forEach(d => { html += '<div>' + d.cantidad + ' x ' + d.descripcion_producto + ' (Bs ' + d.precio_unitario + ' c/u) = Bs ' + d.subtotal + '</div>'; });
    html += '<hr style="border:none;border-top:2px solid var(--gc-texto);margin:8px 0;"><div><strong>Total: Bs ' + c.total + '</strong></div><div>Comprobante: ' + (c.comprobante_numero || '—') + '</div>';
  }
  document.getElementById('detCuerpo').innerHTML = html;
  document.getElementById('modalDetalle').classList.add('abierto');
}
function mostrarToast(mensaje, tipo) {
  let t = document.getElementById('toastGiseca');
  if (!t) { t = document.createElement('div'); t.id = 'toastGiseca'; t.className = 'toast-giseca'; document.body.appendChild(t); }
  t.textContent = mensaje; t.className = 'toast-giseca mostrar ' + (tipo || '');
  setTimeout(() => t.classList.remove('mostrar'), 2800);
}
@if(session('exito')) mostrarToast(@json(session('exito')), 'exito'); @endif
@if(session('error')) mostrarToast(@json(session('error')), 'error'); @endif

function descargarPlantilla() {
  if (typeof XLSX === 'undefined') { mostrarToast('Sin conexión para cargar SheetJS', 'error'); return; }
  const datos = [
    { Numero: 'SF-00001', Fecha: '{{ date("Y-m-d") }}', Proveedor: 'Proveedor Ejemplo SRL', Tipo: 'SIN_FACTURA', Codigo: 'LF9009', Descripcion: 'Filtro de aceite', Cantidad: 5, Costo: 45, Descuento: 0 },
    { Numero: 'SF-00001', Fecha: '{{ date("Y-m-d") }}', Proveedor: 'Proveedor Ejemplo SRL', Tipo: 'SIN_FACTURA', Codigo: 'FF5320', Descripcion: 'Filtro de combustible', Cantidad: 3, Costo: 38, Descuento: 0 },
  ];
  const hoja = XLSX.utils.json_to_sheet(datos);
  const libro = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(libro, hoja, 'Compras');
  XLSX.writeFile(libro, 'plantilla_compras_giseca.xlsx');
}
async function procesarExcel() {
  if (typeof XLSX === 'undefined') { mostrarToast('Sin conexión para cargar SheetJS', 'error'); return; }
  const archivo = document.getElementById('archivoExcel').files[0];
  if (!archivo) { mostrarToast('Selecciona un archivo Excel', 'error'); return; }
  const btn = document.getElementById('btnExcel'); btn.disabled = true;
  const lector = new FileReader();
  lector.onload = async (e) => {
    try {
      const libro = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
      const filas = XLSX.utils.sheet_to_json(libro.Sheets[libro.SheetNames[0]], { defval: '' });
      const resp = await fetch("{{ route('compras.importar-excel') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
        body: JSON.stringify({ filas }),
      });
      const r = await resp.json();
      if (!resp.ok) throw new Error(r.message || 'Error en el servidor.');
      document.getElementById('resultadoExcel').innerHTML = '<div style="background:var(--gc-verde-suave);color:var(--gc-verde);padding:10px;border-radius:6px;font-size:13px;">' + r.creados + ' compra(s), ' + r.duplicadas + ' duplicada(s).</div>' + (r.errores.length ? '<div style="color:var(--gc-rojo);font-size:12px;margin-top:6px;">' + r.errores.join('<br>') + '</div>' : '');
      mostrarToast(r.message, 'exito');
      setTimeout(() => window.location.reload(), 1500);
    } catch (err) {
      document.getElementById('resultadoExcel').innerHTML = '<div style="background:var(--gc-rojo-suave);color:var(--gc-rojo);padding:10px;border-radius:6px;font-size:12.5px;">' + err.message + '</div>';
    } finally { btn.disabled = false; }
  };
  lector.readAsArrayBuffer(archivo);
}
</script>
@endpush
