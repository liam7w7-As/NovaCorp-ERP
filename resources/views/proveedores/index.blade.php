@extends('layouts.app')

@section('title', 'Proveedores')

@section('content')
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('proveedores.index') }}" style="margin:0; flex-grow:1; max-width:340px;">
    <input type="text" id="buscador" name="q" class="form-control-giseca" style="max-width:340px;" placeholder="Buscar por nombre o NIT..." value="{{ $q }}">
  </form>
  <div style="display:flex; gap:8px;">
    <button class="btn-giseca btn-outline" onclick="document.getElementById('modalImportar').classList.add('abierto')"><i class="bi bi-file-earmark-arrow-up"></i> Importar CSV</button>
    <button class="btn-giseca btn-primario" onclick="abrirModal()"><i class="bi bi-plus-lg"></i> Nuevo Proveedor</button>
  </div>
</div>

<div class="modal-giseca" id="modalImportar">
  <div class="modal-box">
    <h6>Importar Proveedores desde CSV</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Columnas: <code>Nombre,NIT,Telefono,Correo,Contacto,Direccion</code>. Los duplicados (mismo nombre y NIT) se omiten.</p>
    <form method="POST" action="{{ route('proveedores.importar') }}" enctype="multipart/form-data">@csrf<input type="file" name="archivo" accept=".csv,.txt" class="form-control-giseca" style="margin-bottom:12px;" required><div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario">Importar</button><button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalImportar').classList.remove('abierto')">Cerrar</button></div></form>
  </div>
</div>

@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">
    {{ $errors->first() }}
  </div>
@endif

<div style="display:grid; grid-template-columns:1.1fr 1fr; gap:18px;">
  <div class="card-giseca" style="padding:0; overflow:hidden; align-self:start;">
    <table class="tabla-giseca">
      <thead><tr>@include('comunes.sort-th', ['campo' => 'nombre', 'etiqueta' => 'Proveedor', 'sort' => $sort ?? 'nombre', 'dir' => $dir ?? 'asc'])@include('comunes.sort-th', ['campo' => 'nit', 'etiqueta' => 'NIT', 'sort' => $sort ?? 'nombre', 'dir' => $dir ?? 'asc'])<th>Teléfono</th><th></th></tr></thead>
      <tbody id="tbodyProveedores">
        @forelse($proveedores as $p)
          <tr style="cursor:pointer; {{ $seleccionado && $seleccionado->id === $p->id ? 'background:var(--gc-primario-suave);' : '' }}"
              data-search="{{ strtolower($p->nombre.' '.($p->nit ?? '')) }}"
              onclick="window.location='{{ route('proveedores.index', ['ver' => $p->id, 'q' => $q, 'page' => $proveedores->currentPage()]) }}'">
            <td><strong>{{ $p->nombre }}</strong></td>
            <td>{{ $p->nit ?: '—' }}</td>
            <td>{{ $p->telefono ?: '—' }}</td>
            <td style="white-space:nowrap;" onclick="event.stopPropagation();">
              <button class="btn-giseca btn-outline btn-icon btn-sm" title="Editar" onclick='editarProveedor(@json($p))'><i class="bi bi-pencil"></i></button>
              @can('admin')<form method="POST" action="{{ route('proveedores.destroy', $p) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar proveedor {{ $p->nombre }}?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
              </form>@endcan
              <i class="bi bi-chevron-right" style="color:var(--gc-gris-claro); margin-left:4px;"></i>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:30px;">Sin proveedores registrados.</td></tr>
        @endforelse
      </tbody>
    </table>
    @if($proveedores->hasPages())
      <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; font-size:12px; color:var(--gc-gris); border-top:1px solid var(--gc-borde);">
        <span>{{ $proveedores->total() }} proveedor(es)</span>
        <div style="display:flex; gap:8px;">
          @if(!$proveedores->onFirstPage())
            <a class="btn-giseca btn-outline btn-sm" href="{{ $proveedores->previousPageUrl() }}">← Anterior</a>
          @endif
          @if($proveedores->hasMorePages())
            <a class="btn-giseca btn-outline btn-sm" href="{{ $proveedores->nextPageUrl() }}">Siguiente →</a>
          @endif
        </div>
      </div>
    @endif
  </div>
  <div id="fichaProveedor">
    @if($seleccionado)
      <div class="card-giseca" style="margin-bottom:14px;">
        <div style="display:flex; justify-content:space-between; align-items:start;">
          <h6>{{ $seleccionado->nombre }}</h6>
          <button class="btn-giseca btn-outline btn-sm" onclick='editarProveedor(@json($seleccionado))'><i class="bi bi-pencil"></i> Editar</button>
        </div>
        <div style="font-size:13px; color:var(--gc-gris); line-height:1.9;">
          <div><strong>NIT:</strong> {{ $seleccionado->nit ?: '—' }}</div>
          <div><strong>Teléfono:</strong> {{ $seleccionado->telefono ?: '—' }}</div>
          <div><strong>Dirección:</strong> {{ $seleccionado->direccion ?: '—' }}</div>
          <div><strong>Correo:</strong> {{ $seleccionado->correo ?: '—' }}</div>
          <div><strong>Contacto:</strong> {{ $seleccionado->contacto ?: '—' }}</div>
        </div>
      </div>
      <div class="card-giseca">
        <h6>Historial de compras (0)</h6>
        <table class="tabla-giseca"><tbody>
          <tr><td style="color:var(--gc-gris-claro); font-size:12.5px;">Sin compras registradas. El historial estará disponible desde la Fase de Compras.</td></tr>
        </tbody></table>
      </div>
    @else
      <div class="card-giseca" style="text-align:center; color:var(--gc-gris-claro); font-size:13px; padding:40px 20px;">
        <i class="bi bi-truck" style="font-size:28px; display:block; margin-bottom:10px; opacity:.5;"></i>
        Selecciona un proveedor para ver su ficha.
      </div>
    @endif
  </div>
</div>

<div class="modal-giseca" id="modalProveedor">
  <div class="modal-box">
    <h6 id="modalTituloProveedor">Nuevo Proveedor</h6>
    <form id="formProveedor" method="POST" action="{{ route('proveedores.store') }}">
      @csrf
      <div id="methodProveedor"></div>
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Nombre / Razón social *</label><input class="form-control-giseca" id="f_nombre" name="nombre" required></div>
        <div><label class="form-label-giseca">NIT</label><input class="form-control-giseca" id="f_nit" name="nit"></div>
        <div><label class="form-label-giseca">Teléfono</label><input class="form-control-giseca" id="f_telefono" name="telefono"></div>
        <div><label class="form-label-giseca">Correo</label><input type="email" class="form-control-giseca" id="f_correo" name="correo"></div>
        <div><label class="form-label-giseca">Persona de contacto</label><input class="form-control-giseca" id="f_contacto" name="contacto"></div>
        <div><label class="form-label-giseca">Dirección</label><input class="form-control-giseca" id="f_direccion" name="direccion"></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button type="submit" class="btn-giseca btn-primario">Guardar</button>
        <button type="button" class="btn-giseca btn-outline" onclick="cerrarModal()">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const b = document.getElementById('buscador');
  if (b) {
    b.addEventListener('input', function(){
      const t = this.value.toLowerCase();
      document.querySelectorAll('#tbodyProveedores tr[data-search]').forEach(tr => {
        tr.style.display = (!t || tr.getAttribute('data-search').includes(t)) ? '' : 'none';
      });
    });
    b.addEventListener('keydown', function(e){
      if (e.key === 'Enter') { e.preventDefault(); this.form.submit(); }
    });
  }
})();

function abrirModal() {
  document.getElementById('modalTituloProveedor').textContent = 'Nuevo Proveedor';
  document.getElementById('formProveedor').action = "{{ route('proveedores.store') }}";
  document.getElementById('methodProveedor').innerHTML = '';
  ['f_nombre','f_nit','f_telefono','f_correo','f_contacto','f_direccion'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('modalProveedor').classList.add('abierto');
}
function editarProveedor(p) {
  document.getElementById('modalTituloProveedor').textContent = 'Editar Proveedor';
  document.getElementById('formProveedor').action = "/proveedores/" + p.id;
  document.getElementById('methodProveedor').innerHTML = '@method("PUT")';
  document.getElementById('f_nombre').value = p.nombre || '';
  document.getElementById('f_nit').value = p.nit || '';
  document.getElementById('f_telefono').value = p.telefono || '';
  document.getElementById('f_correo').value = p.correo || '';
  document.getElementById('f_contacto').value = p.contacto || '';
  document.getElementById('f_direccion').value = p.direccion || '';
  document.getElementById('modalProveedor').classList.add('abierto');
}
function cerrarModal() { document.getElementById('modalProveedor').classList.remove('abierto'); }

function mostrarToast(mensaje, tipo) {
  let toast = document.getElementById('toastGiseca');
  if (!toast) { toast = document.createElement('div'); toast.id = 'toastGiseca'; toast.className = 'toast-giseca'; document.body.appendChild(toast); }
  toast.textContent = mensaje;
  toast.className = 'toast-giseca mostrar ' + (tipo || '');
  setTimeout(() => toast.classList.remove('mostrar'), 2800);
}
@if(session('exito'))
  mostrarToast(@json(session('exito')), 'exito');
@endif
@if(session('error'))
  mostrarToast(@json(session('error')), 'error');
@endif
</script>
@endpush
