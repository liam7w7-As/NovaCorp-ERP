@extends('layouts.app')

@section('title', 'Sucursales y Puntos de Venta (POS)')

@section('content')
{{-- Banner de cabecera y contexto activo --}}
<div class="card-giseca" style="margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <div style="display:flex; align-items:center; gap:8px;">
        <h5 style="margin:0; font-weight:700;">Sucursales y Puntos de Venta (SIAT)</h5>
        <span class="codigo-chip" style="background:var(--gc-primario-suave); color:var(--gc-primario-oscuro); font-weight:700;">
          <i class="bi bi-geo-alt-fill"></i> Activa: {{ $sucursalActual->nombre }} (Cod. {{ $sucursalActual->codigo }}) · {{ $puntoVentaActual->nombre }} (POS {{ $puntoVentaActual->codigo }})
        </span>
      </div>
      <div style="font-size:12.5px; color:var(--gc-gris); margin-top:4px;">
        Administración de Casa Matriz y agencias fiscales. Cada punto de venta mantiene su ciclo independiente de CUIS (anual) y CUFD (diario).
      </div>
    </div>
    <div style="display:flex; gap:8px;">
      <button type="button" class="btn-giseca btn-primario" onclick="abrirModalSucursal()">
        <i class="bi bi-plus-lg"></i> Nueva Sucursal
      </button>
    </div>
  </div>
</div>

{{-- Listado de Sucursales y sus Puntos de Venta --}}
<div style="display:grid; gap:20px;">
  @forelse($sucursales as $sucursal)
    <div class="card-giseca" style="padding:0; overflow:hidden; border: {{ $sucursal->id === $sucursalActual->id ? '2px solid var(--gc-primario)' : '1px solid var(--gc-borde)' }};">
      {{-- Cabecera de la Sucursal --}}
      <div style="padding:14px 18px; background:var(--gc-superficie); border-bottom:1px solid var(--gc-borde); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <span class="codigo-chip" style="font-size:13px; font-weight:800; padding:4px 10px; background:var(--gc-primario-suave); color:var(--gc-primario-oscuro);">
            Código {{ $sucursal->codigo }}
          </span>
          <div>
            <h6 style="margin:0; font-weight:700; display:flex; align-items:center; gap:8px;">
              {{ $sucursal->nombre }}
              @if($sucursal->codigo === 0)
                <span class="codigo-chip" style="font-size:10px; background:#e0f2fe; color:#0369a1;">CASA MATRIZ</span>
              @endif
              @if($sucursal->id === $sucursalActual->id)
                <span class="estado estado-aprobada" style="font-size:10px;"><i class="bi bi-check-circle-fill"></i> SESIÓN ACTIVA</span>
              @endif
            </h6>
            <div style="font-size:12px; color:var(--gc-gris); margin-top:2px;">
              <i class="bi bi-geo-alt"></i> {{ $sucursal->direccion ?: 'Sin dirección' }} · {{ $sucursal->municipio }}
              @if($sucursal->telefono) · <i class="bi bi-telephone"></i> {{ $sucursal->telefono }} @endif
            </div>
          </div>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
          <button type="button" class="btn-giseca btn-outline btn-sm" onclick="abrirModalPuntoVenta({{ $sucursal->id }}, '{{ $sucursal->nombre }}')">
            <i class="bi bi-plus-circle"></i> Agregar POS
          </button>
          <button type="button" class="btn-giseca btn-outline btn-sm" onclick='editarSucursal(@json($sucursal))'>
            <i class="bi bi-pencil"></i>
          </button>
          @if($sucursal->codigo !== 0)
            <form method="POST" action="{{ route('sucursales.destroy', $sucursal) }}" style="margin:0;" onsubmit="return confirm('¿Seguro de eliminar esta sucursal?');">
              @csrf @method('DELETE')
              <button type="submit" class="btn-giseca btn-outline btn-sm" style="color:var(--gc-rojo);">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          @endif
        </div>
      </div>

      {{-- Tabla de Puntos de Venta (POS) --}}
      <div style="padding:0; overflow-x:auto;">
        <table class="tabla-giseca" style="margin:0;">
          <thead>
            <tr>
              <th style="width:110px;">Código POS</th>
              <th>Nombre del Punto de Venta</th>
              <th>Tipo</th>
              <th>Estado CUIS (Anual)</th>
              <th>Estado CUFD (24 Horas)</th>
              <th style="text-align:right; width:220px;">Acciones Fiscales</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sucursal->puntosVenta as $pv)
              @php
                $esActivoSesion = ($sucursal->id === $sucursalActual->id && $pv->id === $puntoVentaActual->id);
                $cuisOk = $pv->tieneCuisVigente();
                $cufdOk = $pv->tieneCufdVigente();
              @endphp
              <tr style="{{ $esActivoSesion ? 'background:rgba(37,99,235,0.03);' : '' }}">
                <td>
                  <span class="codigo-chip" style="font-weight:700;">POS {{ $pv->codigo }}</span>
                </td>
                <td>
                  <strong>{{ $pv->nombre }}</strong>
                  @if($esActivoSesion)
                    <span class="estado estado-aprobada" style="margin-left:6px; font-size:10px;">EN USO</span>
                  @endif
                </td>
                <td style="font-size:12px; color:var(--gc-gris);">
                  {{ $pv->tipo_punto_venta }}
                </td>
                <td>
                  @if($cuisOk)
                    <span class="estado estado-aprobada" title="Vigencia hasta: {{ $pv->cuis_vigencia?->format('d/m/Y') }}">
                      <i class="bi bi-shield-check"></i> CUIS VIGENTE
                    </span>
                    <div style="font-size:10.5px; color:var(--gc-gris); font-family:monospace; margin-top:2px;">{{ substr($pv->cuis, 0, 14) }}...</div>
                  @else
                    <span class="estado estado-rechazada">
                      <i class="bi bi-shield-x"></i> SIN CUIS
                    </span>
                  @endif
                </td>
                <td>
                  @if($cufdOk)
                    <span class="estado estado-aprobada" title="Vence: {{ $pv->cufd_vigencia?->format('d/m/Y H:i') }}">
                      <i class="bi bi-clock-check"></i> CUFD VIGENTE
                    </span>
                    <div style="font-size:10.5px; color:var(--gc-gris); margin-top:2px;">Ctrl: {{ $pv->codigo_control ?: 'SIM-CTRL' }}</div>
                  @else
                    <span class="estado estado-vencida">
                      <i class="bi bi-clock-history"></i> CUFD EXPIRADO
                    </span>
                  @endif
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  {{-- Botón CUIS --}}
                  <form method="POST" action="{{ route('sucursales.puntos-venta.cuis', $pv) }}" style="display:inline; margin:0;">
                    @csrf
                    <button type="submit" class="btn-giseca btn-outline btn-sm" title="Solicitar nuevo CUIS al SIN">
                      <i class="bi bi-key"></i> CUIS
                    </button>
                  </form>

                  {{-- Botón CUFD --}}
                  <form method="POST" action="{{ route('sucursales.puntos-venta.cufd', $pv) }}" style="display:inline; margin:0;">
                    @csrf
                    <button type="submit" class="btn-giseca btn-outline btn-sm" title="Solicitar nuevo CUFD diario al SIN">
                      <i class="bi bi-arrow-repeat"></i> CUFD
                    </button>
                  </form>

                  {{-- Seleccionar como activo --}}
                  @if(! $esActivoSesion)
                    <form method="POST" action="{{ route('sucursales.cambiar-activa') }}" style="display:inline; margin:0;">
                      @csrf
                      <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">
                      <input type="hidden" name="punto_venta_id" value="{{ $pv->id }}">
                      <button type="submit" class="btn-giseca btn-primario btn-sm" title="Seleccionar para emitir facturas">
                        <i class="bi bi-check2"></i> Activar
                      </button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" style="text-align:center; padding:20px; color:var(--gc-gris-claro);">
                  Sin puntos de venta registrados. Pulsa "Agregar POS".
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @empty
    <div class="card-giseca" style="text-align:center; padding:40px; color:var(--gc-gris-claro);">
      Sin sucursales registradas.
    </div>
  @endforelse
</div>

{{-- Modal Crear/Editar Sucursal --}}
<div class="modal-giseca" id="modalSucursal">
  <div class="modal-box">
    <h6 id="modalSucursalTitulo">Nueva Sucursal</h6>
    <form id="formSucursal" method="POST" action="{{ route('sucursales.store') }}">
      @csrf
      <div id="methodSucursal"></div>
      <div style="display:grid; grid-template-columns:1fr 2fr; gap:10px;">
        <div>
          <label class="form-label-giseca">Código Fiscal (SIN) *</label>
          <input type="number" min="0" class="form-control-giseca" id="suc_codigo" name="codigo" placeholder="Ej: 1" required>
        </div>
        <div>
          <label class="form-label-giseca">Nombre de la Sucursal *</label>
          <input type="text" class="form-control-giseca" id="suc_nombre" name="nombre" placeholder="Ej: Sucursal Equipetrol" required>
        </div>
      </div>
      <div style="margin-top:10px;">
        <label class="form-label-giseca">Dirección Comercial</label>
        <input type="text" class="form-control-giseca" id="suc_direccion" name="direccion" placeholder="Ej: Av. San Martín #450">
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
        <div>
          <label class="form-label-giseca">Municipio / Ciudad *</label>
          <input type="text" class="form-control-giseca" id="suc_municipio" name="municipio" value="Santa Cruz de la Sierra" required>
        </div>
        <div>
          <label class="form-label-giseca">Teléfono</label>
          <input type="text" class="form-control-giseca" id="suc_telefono" name="telefono" placeholder="Ej: 3-3334444">
        </div>
      </div>
      <div style="margin-top:10px;" id="divActivaSucursal">
        <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:13px;">
          <input type="checkbox" name="activa" id="suc_activa" value="1" checked> Sucursal activa para operaciones
        </label>
      </div>
      <div style="display:flex; gap:8px; margin-top:18px;">
        <button type="submit" class="btn-giseca btn-primario">Guardar Sucursal</button>
        <button type="button" class="btn-giseca btn-outline" onclick="cerrarModal('modalSucursal')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Crear Punto de Venta --}}
<div class="modal-giseca" id="modalPuntoVenta">
  <div class="modal-box">
    <h6>Registrar Punto de Venta (POS)</h6>
    <div style="font-size:12.5px; color:var(--gc-gris); margin-bottom:12px;" id="pv_sucursalTexto"></div>
    <form id="formPuntoVenta" method="POST" action="">
      @csrf
      <div style="display:grid; grid-template-columns:1fr 2fr; gap:10px;">
        <div>
          <label class="form-label-giseca">Código POS (SIN) *</label>
          <input type="number" min="0" class="form-control-giseca" id="pv_codigo" name="codigo" placeholder="Ej: 1" required>
        </div>
        <div>
          <label class="form-label-giseca">Nombre del POS *</label>
          <input type="text" class="form-control-giseca" id="pv_nombre" name="nombre" placeholder="Ej: Caja Mostrador 1" required>
        </div>
      </div>
      <div style="margin-top:10px;">
        <label class="form-label-giseca">Tipo de Punto de Venta *</label>
        <select class="form-control-giseca" id="pv_tipo" name="tipo_punto_venta" required>
          <option value="Punto de Venta Fijo">1 — Punto de Venta Fijo</option>
          <option value="Punto de Venta Móvil">2 — Punto de Venta Móvil</option>
          <option value="Comercio Electrónico / Web">3 — Comercio Electrónico / Web</option>
        </select>
      </div>
      <div style="display:flex; gap:8px; margin-top:18px;">
        <button type="submit" class="btn-giseca btn-primario">Registrar POS</button>
        <button type="button" class="btn-giseca btn-outline" onclick="cerrarModal('modalPuntoVenta')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function abrirModalSucursal() {
  document.getElementById('modalSucursalTitulo').textContent = 'Nueva Sucursal';
  document.getElementById('formSucursal').action = "{{ route('sucursales.store') }}";
  document.getElementById('methodSucursal').innerHTML = '';
  document.getElementById('suc_codigo').value = '';
  document.getElementById('suc_codigo').readOnly = false;
  document.getElementById('suc_nombre').value = '';
  document.getElementById('suc_direccion').value = '';
  document.getElementById('suc_telefono').value = '';
  document.getElementById('suc_municipio').value = 'Santa Cruz de la Sierra';
  document.getElementById('suc_activa').checked = true;
  document.getElementById('modalSucursal').classList.add('abierto');
}

function editarSucursal(s) {
  document.getElementById('modalSucursalTitulo').textContent = 'Editar Sucursal: ' + s.nombre;
  document.getElementById('formSucursal').action = "/sucursales/" + s.id;
  document.getElementById('methodSucursal').innerHTML = '@method("PUT")';
  document.getElementById('suc_codigo').value = s.codigo;
  document.getElementById('suc_codigo').readOnly = (s.codigo === 0);
  document.getElementById('suc_nombre').value = s.nombre;
  document.getElementById('suc_direccion').value = s.direccion || '';
  document.getElementById('suc_telefono').value = s.telefono || '';
  document.getElementById('suc_municipio').value = s.municipio || 'Santa Cruz de la Sierra';
  document.getElementById('suc_activa').checked = Boolean(s.activa);
  document.getElementById('modalSucursal').classList.add('abierto');
}

function abrirModalPuntoVenta(sucursalId, sucursalNombre) {
  document.getElementById('pv_sucursalTexto').textContent = 'Sucursal asociada: ' + sucursalNombre;
  document.getElementById('formPuntoVenta').action = "/sucursales/" + sucursalId + "/puntos-venta";
  document.getElementById('pv_codigo').value = '';
  document.getElementById('pv_nombre').value = '';
  document.getElementById('modalPuntoVenta').classList.add('abierto');
}

function cerrarModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('abierto');
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
  setTimeout(() => t.classList.remove('mostrar'), 2600);
}

@if(session('exito')) mostrarToast(@json(session('exito')), 'exito'); @endif
@if(session('error')) mostrarToast(@json(session('error')), 'error'); @endif
</script>
@endpush
