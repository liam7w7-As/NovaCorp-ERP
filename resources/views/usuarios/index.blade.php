@extends('layouts.app')

@section('title', 'Usuarios y Permisos')

@section('content')
<div style="display:flex; gap:8px; margin-bottom:16px;">
  <button class="btn-giseca btn-primario" onclick="document.getElementById('modalUsuario').classList.add('abierto')"><i class="bi bi-plus-lg"></i> Nuevo Usuario</button>
  <button class="btn-giseca btn-outline" onclick="document.getElementById('modalRol').classList.add('abierto')"><i class="bi bi-plus-lg"></i> Nuevo Rol</button>
</div>

@if($errors->any())
  <div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="card-giseca" style="padding:0; overflow:hidden; margin-bottom:18px;">
  <div style="padding:12px 18px; font-weight:700;">Usuarios</div>
  <table class="tabla-giseca">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      @foreach($usuarios as $u)
        <tr>
          <td><strong>{{ $u->name }}</strong>@if($u->id === auth()->id()) <span class="estado estado-enviada">TÚ</span>@endif</td>
          <td>{{ $u->email }}</td>
          <td><span class="estado {{ $u->rol === 'admin' ? 'estado-convertida' : 'estado-borrador' }}">{{ strtoupper($u->rol) }}</span></td>
          <td><span class="estado {{ $u->activo ? 'estado-aprobada' : 'estado-rechazada' }}">{{ $u->activo ? 'ACTIVO' : 'INACTIVO' }}</span></td>
          <td class="text-end" style="white-space:nowrap;">
            <button class="btn-giseca btn-outline btn-icon btn-sm" title="Editar" onclick='editarUsuario(@json($u))'><i class="bi bi-pencil"></i></button>
            @if($u->id !== auth()->id())
              <form method="POST" action="{{ route('usuarios.destroy', $u) }}" style="display:inline;" data-confirm="¿Eliminar al usuario {{ $u->name }}?" data-confirm-title="Eliminar usuario" data-confirm-label="Eliminar" data-confirm-variant="peligro">@csrf @method('DELETE')<button class="btn-giseca btn-outline btn-icon btn-sm" title="Eliminar"><i class="bi bi-trash" style="color:var(--gc-rojo);"></i></button></form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="card-giseca" style="padding:0; overflow:hidden;">
  <div style="padding:12px 18px; font-weight:700;">Roles y permisos <span style="font-weight:400; font-size:12px; color:var(--gc-gris);">— clic en el candado para dar/quitar acceso al instante</span></div>
  <div style="padding:0 18px 12px; display:flex; gap:8px; flex-wrap:wrap;">
    @foreach($roles as $r)
      <span class="codigo-chip">{{ $r->nombre }}
        @if(!$r->es_sistema)
          <form method="POST" action="{{ route('roles.destroy', $r) }}" style="display:inline;" data-confirm="¿Eliminar el rol {{ $r->nombre }}?" data-confirm-title="Eliminar rol" data-confirm-label="Eliminar" data-confirm-variant="peligro">@csrf @method('DELETE')<button style="border:none; background:none; cursor:pointer; color:var(--gc-rojo);" title="Eliminar rol">×</button></form>
        @else
          <i class="bi bi-lock-fill" style="color:var(--gc-gris-claro);" title="Rol del sistema"></i>
        @endif
      </span>
    @endforeach
  </div>
  <div style="overflow-x:auto;">
  <table class="tabla-giseca">
    <thead><tr><th>Permiso</th>
      @foreach($roles as $r)<th class="text-center">{{ $r->nombre }}</th>@endforeach
    </tr></thead>
    <tbody>
      @foreach($grupos as $modulo => $habilidades)
        <tr><td colspan="{{ $roles->count() + 1 }}" style="background:var(--gc-fondo); font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--gc-gris);">{{ $modulo }}</td></tr>
        @foreach($habilidades as $h => $desc)
          <tr>
            <td>{{ $desc }}</td>
            @foreach($roles as $r)
              <td class="text-center">
                @if(\App\Services\Permisos::esBloqueada($r->clave))
                  <i class="bi bi-lock-fill" style="color:var(--gc-verde);" title="Acceso total (bloqueado)"></i>
                @else
                  <button class="btn-giseca btn-outline btn-icon btn-sm candado" data-rol="{{ $r->clave }}" data-hab="{{ $h }}" title="Clic para cambiar">
                    <i class="bi {{ ($matriz[$r->clave][$h] ?? false) ? 'bi-unlock-fill' : 'bi-lock-fill' }}" style="color:{{ ($matriz[$r->clave][$h] ?? false) ? 'var(--gc-verde)' : 'var(--gc-gris-claro)' }};"></i>
                  </button>
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>
  </div>
</div>

<!-- Modal Usuario -->
<div class="modal-giseca" id="modalUsuario">
  <div class="modal-box">
    <h6 id="tituloUsuario">Nuevo Usuario</h6>
    <form id="formUsuario" method="POST" action="{{ route('usuarios.store') }}">
      @csrf
      <div id="methodUsuario"></div>
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Nombre *</label><input class="form-control-giseca" id="u_nombre" name="name" required></div>
        <div><label class="form-label-giseca">Correo *</label><input type="email" class="form-control-giseca" id="u_email" name="email" required></div>
        <div><label class="form-label-giseca">Contraseña <span id="u_passHint">(requerida)</span></label><input type="password" class="form-control-giseca" id="u_pass" name="password"></div>
        <div><label class="form-label-giseca">Rol *</label>
          <select class="form-control-giseca" id="u_rol" name="rol">
            @foreach($roles as $r)<option value="{{ $r->clave }}">{{ $r->nombre }}</option>@endforeach
          </select>
        </div>
        <div id="filaActivo"><label style="display:flex; gap:8px; font-size:13px;"><input type="checkbox" name="activo" value="1" id="u_activo" checked> Usuario activo (puede iniciar sesión)</label></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Guardar</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalUsuario').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Rol -->
<div class="modal-giseca" id="modalRol">
  <div class="modal-box">
    <h6>Nuevo Rol</h6>
    <form method="POST" action="{{ route('roles.store') }}">
      @csrf
      <div style="display:grid; gap:10px;">
        <div><label class="form-label-giseca">Nombre *</label><input class="form-control-giseca" name="nombre" placeholder="Ej: Almacenero" required></div>
        <div><label class="form-label-giseca">Descripción</label><input class="form-control-giseca" name="descripcion" placeholder="Qué puede hacer este rol"></div>
      </div>
      <div style="display:flex; gap:8px; margin-top:16px;">
        <button class="btn-giseca btn-primario">Crear rol</button>
        <button type="button" class="btn-giseca btn-outline" onclick="document.getElementById('modalRol').classList.remove('abierto')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function editarUsuario(u) {
  document.getElementById('tituloUsuario').textContent = 'Editar Usuario';
  document.getElementById('formUsuario').action = '/usuarios/' + u.id;
  document.getElementById('methodUsuario').innerHTML = '@method("PUT")';
  document.getElementById('u_nombre').value = u.name || '';
  document.getElementById('u_email').value = u.email || '';
  document.getElementById('u_pass').value = '';
  document.getElementById('u_passHint').textContent = '(vacía = no cambiar)';
  document.getElementById('u_rol').value = u.rol;
  document.getElementById('u_activo').checked = !!u.activo;
  document.getElementById('modalUsuario').classList.add('abierto');
}

document.querySelectorAll('.candado').forEach(btn => {
  btn.addEventListener('click', async () => {
    const rol = btn.dataset.rol, hab = btn.dataset.hab;
    btn.disabled = true;
    try {
      const resp = await fetch("{{ route('permisos.toggle') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
        body: JSON.stringify({ rol, habilidad: hab }),
      });
      const r = await resp.json();
      if (!resp.ok) throw new Error(r.message || 'Error');
      const icon = btn.querySelector('i');
      icon.className = 'bi ' + (r.permitido ? 'bi-unlock-fill' : 'bi-lock-fill');
      icon.style.color = r.permitido ? 'var(--gc-verde)' : 'var(--gc-gris-claro)';
      mostrarToast(r.permitido ? 'Permiso otorgado' : 'Permiso revocado', 'exito');
    } catch (err) {
      mostrarToast(err.message, 'error');
    } finally {
      btn.disabled = false;
    }
  });
});

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
