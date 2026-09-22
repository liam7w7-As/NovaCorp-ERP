@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <form method="GET" action="{{ route('clientes.index') }}" style="margin:0; flex-grow:1; max-width:340px;">
    <input type="text" id="buscador" name="q" class="form-control-giseca" style="max-width:340px;" placeholder="Buscar por nombre o NIT/CI..." value="{{ $q }}">
  </form>
  <div style="display:flex; gap:8px;">
    <button class="btn-giseca btn-outline" onclick="document.getElementById('modalImportar').classList.add('abierto')"><i class="bi bi-file-earmark-arrow-up"></i> Importar CSV</button>
    <button class="btn-giseca btn-primario" onclick="abrirModal()"><i class="bi bi-plus-lg"></i> Nuevo Cliente</button>
  </div>
</div>

<div class="modal-giseca" id="modalImportar">
  <div class="modal-box">
    <h6>Importar Clientes desde CSV</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Columnas: <code>Nombre,NIT,Telefono,Correo,Contacto,Direccion</code>. Los duplicados (mismo nombre y NIT) se omiten.</p>
    <form method="POST" action="{{ route('clientes.importar') }}" enctype="multipart/form-data">@csrf<input type="file" name="archivo" accept=".csv,.txt" class="form-control-giseca" style="margin-bottom:12px;" required><div style="display:flex; gap:8px; margin-top:14px;"><button class="btn-giseca btn-primario">Importar</button><button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalImportar').classList.remove('abierto')">Cerrar</button></div></form>
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
      <thead><tr>@include('comunes.sort-th', ['campo' => 'nombre', 'etiqueta' => 'Cliente', 'sort' => $sort ?? 'nombre', 'dir' => $dir ?? 'asc'])@include('comunes.sort-th', ['campo' => 'nit', 'etiqueta' => 'NIT/CI', 'sort' => $sort ?? 'nombre', 'dir' => $dir ?? 'asc'])<th>Teléfono</th><th></th></tr></thead>
      <tbody id="tbodyClientes">
        @forelse($clientes as $c)
          <tr style="cursor:pointer; {{ $seleccionado && $seleccionado->id === $c->id ? 'background:var(--gc-primario-suave);' : '' }}"
              data-search="{{ strtolower($c->nombre.' '.($c->nit ?? '')) }}"
              onclick="window.location='{{ route('clientes.index', ['ver' => $c->id, 'q' => $q, 'page' => $clientes->currentPage()]) }}'">
            <td><strong>{{ $c->nombre }}</strong></td>
            <td>{{ $c->nit ?: '—' }}</td>
            <td>{{ $c->telefono ?: '—' }}</td>
            <td style="white-space:nowrap;" onclick="event.stopPropagation();">
              <button class="btn-giseca btn-outline btn-icon btn-sm" title="Editar" onclick='editarCliente(@json($c))'><i class="bi bi-pencil"></i></button>
              @can('admin')<form method="POST" action="{{ route('clientes.destroy', $c) }}" style="display:inline;" data-confirm="¿Eliminar al cliente {{ $c->nombre }}?" data-confirm-title="Eliminar cliente" data-confirm-label="Eliminar" data-confirm-variant="peligro">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
              </form>@endcan
              <i class="bi bi-chevron-right" style="color:var(--gc-gris-claro); margin-left:4px;"></i>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center; color:var(--gc-gris-claro); padding:30px;">Sin clientes registrados.</td></tr>
        @endforelse
      </tbody>
    </table>
    @if($clientes->hasPages())
      <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; font-size:12px; color:var(--gc-gris); border-top:1px solid var(--gc-borde);">
        <span>{{ $clientes->total() }} cliente(s)</span>
        <div style="display:flex; gap:8px;">
          @if(!$clientes->onFirstPage())
            <a class="btn-giseca btn-outline btn-sm" href="{{ $clientes->previousPageUrl() }}">← Anterior</a>
          @endif
          @if($clientes->hasMorePages())
            <a class="btn-giseca btn-outline btn-sm" href="{{ $clientes->nextPageUrl() }}">Siguiente →</a>
          @endif
        </div>
      </div>
    @endif
  </div>
  <div id="fichaCliente">
    @if($seleccionado)
      <div class="card-giseca" style="margin-bottom:14px;">
        <div style="display:flex; justify-content:space-between; align-items:start;">
          <h6>{{ $seleccionado->nombre }}</h6>
          <button class="btn-giseca btn-outline btn-sm" onclick='editarCliente(@json($seleccionado))'><i class="bi bi-pencil"></i> Editar</button>
        </div>
        <div style="font-size:13px; color:var(--gc-gris); line-height:1.9;">
          <div><strong>{{ [1 => 'CI', 2 => 'CEX', 3 => 'PAS', 4 => 'OD', 5 => 'NIT'][$seleccionado->codigo_tipo_documento] ?? 'Documento' }}:</strong> {{ $seleccionado->nit ?: '—' }}
            @if($seleccionado->nit)
              @php $okDigito = \App\Services\NitHelper::coincideDigito($seleccionado->nit); @endphp
              @if($okDigito === true)
                <span class="estado estado-aprobada" title="Dígito verificador módulo 11 correcto (validación local)">DÍGITO OK</span>
              @elseif($okDigito === false)
                <span class="estado estado-vencida" title="El dígito verificador no coincide (validación local). Verifícalo con el SIN antes de facturar">REVISAR DÍGITO</span>
              @endif
            @endif
          </div>
          <div><strong>Teléfono:</strong> {{ $seleccionado->telefono ?: '—' }}</div>
          <div><strong>Dirección:</strong> {{ $seleccionado->direccion ?: '—' }}</div>
          <div><strong>Correo:</strong> {{ $seleccionado->correo ?: '—' }}</div>
          <div><strong>Contacto:</strong> {{ $seleccionado->contacto ?: '—' }}</div>
        </div>
      </div>
      <div class="card-giseca">
        <h6>Historial de proformas (0)</h6>
        <table class="tabla-giseca"><tbody>
          <tr><td style="color:var(--gc-gris-claro); font-size:12.5px;">Sin proformas registradas. El historial estará disponible desde la Fase de Proformas.</td></tr>
        </tbody></table>
      </div>
    @else
      <div class="card-giseca" style="text-align:center; color:var(--gc-gris-claro); font-size:13px; padding:40px 20px;">
        <i class="bi bi-person-vcard" style="font-size:28px; display:block; margin-bottom:10px; opacity:.5;"></i>
        Selecciona un cliente para ver su ficha.
      </div>
    @endif
  </div>
</div>

<div class="modal-giseca" id="modalCliente">
  <div class="modal-box">
    <h6 id="modalTituloCliente">Nuevo Cliente</h6>
    <form id="formCliente" method="POST" action="{{ route('clientes.store') }}">
      @csrf
      <div id="methodCliente"></div>
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Nombre / Razón social *</label><input class="form-control-giseca" id="f_nombre" name="nombre" required></div>
        <div style="display:grid; grid-template-columns:minmax(110px,.45fr) minmax(0,1fr); gap:10px;">
          <div><label class="form-label-giseca">Tipo documento</label><select class="form-control-giseca" id="f_codigo_tipo_documento" name="codigo_tipo_documento" required><option value="5">NIT</option><option value="1">CI</option><option value="2">CEX</option><option value="3">Pasaporte</option><option value="4">Otro</option></select></div>
          <div><label class="form-label-giseca">Número</label><input class="form-control-giseca" id="f_nit" name="nit"></div>
        </div>
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
      document.querySelectorAll('#tbodyClientes tr[data-search]').forEach(tr => {
        tr.style.display = (!t || tr.getAttribute('data-search').includes(t)) ? '' : 'none';
      });
    });
    b.addEventListener('keydown', function(e){
      if (e.key === 'Enter') { e.preventDefault(); this.form.submit(); }
    });
  }
})();

function abrirModal() {
  document.getElementById('modalTituloCliente').textContent = 'Nuevo Cliente';
  document.getElementById('formCliente').action = "{{ route('clientes.store') }}";
  document.getElementById('methodCliente').innerHTML = '';
  ['f_nombre','f_nit','f_telefono','f_correo','f_contacto','f_direccion'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f_codigo_tipo_documento').value = '5';
  document.getElementById('modalCliente').classList.add('abierto');
}
function editarCliente(c) {
  document.getElementById('modalTituloCliente').textContent = 'Editar Cliente';
  document.getElementById('formCliente').action = "/clientes/" + c.id;
  document.getElementById('methodCliente').innerHTML = '@method("PUT")';
  document.getElementById('f_nombre').value = c.nombre || '';
  document.getElementById('f_nit').value = c.nit || '';
  document.getElementById('f_codigo_tipo_documento').value = String(c.codigo_tipo_documento || 5);
  document.getElementById('f_telefono').value = c.telefono || '';
  document.getElementById('f_correo').value = c.correo || '';
  document.getElementById('f_contacto').value = c.contacto || '';
  document.getElementById('f_direccion').value = c.direccion || '';
  document.getElementById('modalCliente').classList.add('abierto');
}
function cerrarModal() { document.getElementById('modalCliente').classList.remove('abierto'); }

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
