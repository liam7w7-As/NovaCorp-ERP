@extends('layouts.app')

@section('title', 'Configuración')

@push('styles')
<style>
  .dropzone {
    border: 2px dashed var(--gc-gris-claro);
    border-radius: 10px;
    padding: 34px 20px;
    text-align: center;
    cursor: pointer;
    background: var(--gc-fondo);
  }

  .dropzone:hover,
  .dropzone.dragover {
    border-color: var(--gc-primario);
    background: var(--gc-primario-suave);
  }

  .dropzone i {
    font-size: 32px;
    color: var(--gc-gris-claro);
  }

  .preview-frame {
    border: 1px solid var(--gc-borde);
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .preview-frame img {

    max-width: 100%;

    max-height: 100%;

    object-fit: contain;

    display: block;

    margin: auto;

  }

  .preview-frame embed {
    width: 100%;
    height: 300px;
  }
</style>
@endpush

@section('content')
@if($errors->any())
<div style="background:var(--gc-rojo-suave); color:var(--gc-rojo); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start;">
  <div class="card-giseca" style="margin-bottom:18px;">

    <h6>Logo del Sistema</h6>

    <p style="font-size:12.5px; color:var(--gc-gris);">
      Logo utilizado en login, menú principal y elementos visuales del ERP.
    </p>


    <form method="POST"
      action="{{ route('configuracion.update') }}"
      enctype="multipart/form-data"
      id="formLogo">

      @csrf


      <input type="hidden"
        name="empresa_nombre"
        value="{{ $empresa['nombre'] }}">


      <input type="hidden"
        name="empresa_nit"
        value="{{ $empresa['nit'] }}">


      <input type="hidden"
        name="empresa_direccion"
        value="{{ $empresa['direccion'] }}">


      <input type="hidden"
        name="empresa_telefono"
        value="{{ $empresa['telefono'] }}">


      <input type="hidden"
        name="empresa_email"
        value="{{ $empresa['email'] }}">



      <div class="preview-frame"
        style="height:180px;">


        @if($logo)

        <img

          src="{{ $logo['url'] }}"

          alt="Logo empresa"

          style="
max-height:150px;
object-fit:contain;
">

        @else

        <img

          src="{{ asset('images/logo.png') }}"

          alt="Logo por defecto"

          style="
max-height:150px;
object-fit:contain;
">

        @endif


      </div>



      <div class="dropzone"
        style="margin-top:15px;"
        onclick="document.getElementById('inputLogo').click()">


        <i class="bi bi-image"></i>


        <div style="margin-top:8px;font-weight:600;">
          Seleccionar logo
        </div>


        <div style="font-size:12px;color:var(--gc-gris-claro);">
          PNG o JPG - máximo 2 MB
        </div>


      </div>



      <input

        type="file"

        id="inputLogo"

        name="logo"

        accept=".jpg,.jpeg,.png"

        style="display:none;"

        onchange="document.getElementById('formLogo').submit()">


    </form>



    <form method="POST"
      action="{{ route('configuracion.update') }}"
      style="margin-top:10px;">

      @csrf


      <input type="hidden"
        name="empresa_nombre"
        value="{{ $empresa['nombre'] }}">


      <input type="hidden"
        name="quitar_logo"
        value="1">


      <button

        class="btn-giseca btn-outline btn-sm"

        onclick="return confirm('¿Quitar logo?')">

        Quitar logo

      </button>


    </form>


  </div>
  <div class="card-giseca">
    <h6>Membretado de Cotizaciones</h6>
    <p style="font-size:12.5px; color:var(--gc-gris);">Sube tu papelería oficial (JPG, PNG o PDF). Se aplicará automáticamente al generar cualquier proforma en PDF.</p>

    <form method="POST" action="{{ route('configuracion.update') }}" enctype="multipart/form-data" id="formMembrete">
      @csrf
      <input type="hidden" name="empresa_nombre" value="{{ $empresa['nombre'] }}">
      <input type="hidden" name="empresa_nit" value="{{ $empresa['nit'] }}">
      <input type="hidden" name="empresa_direccion" value="{{ $empresa['direccion'] }}">
      <input type="hidden" name="empresa_telefono" value="{{ $empresa['telefono'] }}">
      <input type="hidden" name="empresa_email" value="{{ $empresa['email'] }}">
      <div class="dropzone" id="dropzone" onclick="document.getElementById('inputMembrete').click()">
        <i class="bi bi-cloud-arrow-up"></i>
        <div style="margin-top:8px; font-weight:600;">Arrastra tu archivo aquí o haz clic para seleccionar</div>
        <div style="font-size:12px; color:var(--gc-gris-claro);">JPG, PNG o PDF — máx. 5 MB</div>
      </div>
      <input type="file" id="inputMembrete" name="membretado" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="document.getElementById('formMembrete').submit()">
    </form>

    @if($membretado)
    <div id="estadoActual" style="margin-top:16px;">
      <div class="preview-frame">
        @if($membretado['tipo'] === 'image')
        <img src="{{ $membretado['url'] }}" alt="Membretado">
        @else
        <embed src="{{ $membretado['url'] }}" type="application/pdf">
        @endif
      </div>
      <div style="display:flex; gap:8px; margin-top:12px;">
        <button class="btn-giseca btn-primario btn-sm" onclick="document.getElementById('inputMembrete').click()">Reemplazar</button>
        <form method="POST" action="{{ route('configuracion.update') }}" style="display:inline;">
          @csrf
          <input type="hidden" name="empresa_nombre" value="{{ $empresa['nombre'] }}">
          <input type="hidden" name="quitar_membretado" value="1">
          <button class="btn-giseca btn-outline btn-sm" onclick="return confirm('¿Quitar el membretado?')">Quitar</button>
        </form>
      </div>
    </div>
    @else
    <div style="margin-top:14px; font-size:12.5px; color:var(--gc-gris-claro);">
      Sin membretado cargado — las proformas usan el encabezado de texto por defecto.
    </div>
    @endif
  </div>

  <div>
    <div class="card-giseca" style="margin-bottom:18px;">
      <h6>Datos de la Empresa</h6>
      <p style="font-size:12.5px; color:var(--gc-gris);">Se usan en el encabezado de proformas y comprobantes.</p>
      <form method="POST" action="{{ route('configuracion.update') }}">
        @csrf
        <div style="display:grid; gap:10px;">
          <div><label class="form-label-giseca">Nombre / Razón social *</label><input name="empresa_nombre" class="form-control-giseca" value="{{ old('empresa_nombre', $empresa['nombre']) }}" required></div>
          <div><label class="form-label-giseca">NIT</label><input name="empresa_nit" class="form-control-giseca" value="{{ old('empresa_nit', $empresa['nit']) }}"></div>
          <div><label class="form-label-giseca">Dirección</label><input name="empresa_direccion" class="form-control-giseca" value="{{ old('empresa_direccion', $empresa['direccion']) }}"></div>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div><label class="form-label-giseca">Teléfono</label><input name="empresa_telefono" class="form-control-giseca" value="{{ old('empresa_telefono', $empresa['telefono']) }}"></div>
            <div><label class="form-label-giseca">Correo</label><input type="email" name="empresa_email" class="form-control-giseca" value="{{ old('empresa_email', $empresa['email']) }}"></div>
          </div>
        </div>
        <button class="btn-giseca btn-primario" style="margin-top:14px;">Guardar datos</button>
      </form>
    </div>

    <div class="card-giseca">
      <h6>Datos del sistema (MySQL)</h6>
      <p style="font-size:12.5px; color:var(--gc-gris);">Toda la información vive en la base de datos del servidor.</p>
      <div style="font-size:13px; line-height:2;">
        <div>📦 Productos: <strong>{{ $conteos['productos'] }}</strong></div>
        <div>👥 Clientes: <strong>{{ $conteos['clientes'] }}</strong></div>
        <div>🚚 Proveedores: <strong>{{ $conteos['proveedores'] }}</strong></div>
        <div>📄 Proformas: <strong>{{ $conteos['proformas'] }}</strong></div>
        <div>🛒 Compras: <strong>{{ $conteos['compras'] }}</strong></div>
        <div>💰 Ventas: <strong>{{ $conteos['ventas'] }}</strong></div>
        <div>🧾 Comprobantes: <strong>{{ $conteos['comprobantes'] }}</strong></div>
      </div>
      <form method="POST" action="{{ route('configuracion.resetear') }}" onsubmit="return confirm('Esto borrará TODOS los datos (productos, clientes, compras, ventas, etc.) y los reiniciará con datos de ejemplo. ¿Continuar?')">
        @csrf
        <div style="display:flex; gap:8px; margin-top:14px; align-items:center;">
          <input name="confirmacion" class="form-control-giseca" placeholder="Escribe REINICIAR para confirmar" style="max-width:220px;" required>
          <button class="btn-giseca btn-outline btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reiniciar datos</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card-giseca" style="margin-top:18px;">
  <h6><i class="bi bi-receipt-cutoff" style="color:var(--gc-primario);"></i> Facturación Electrónica SIAT</h6>
  <p style="font-size:12.5px; color:var(--gc-gris);">
    Modo actual: <strong>{{ strtoupper($siat['modo']) }}</strong> · Ambiente: <strong>{{ strtoupper($siat['ambiente']) }}</strong> ·
    CUIS: <strong>{{ $siat['cuis'] ? 'cargado' : '—' }}</strong> · CUFD: <strong>{{ $siat['cufd'] ? 'cargado (vig. '.$siat['cufd_vigencia'].')' : '—' }}</strong> ·
    Certificado: <strong>{{ $siat['certificado_path'] ?: '—' }}</strong>
    <br>En modo <strong>SIMULADOR</strong> no se llama al SIN: las respuestas se generan localmente y quedan marcadas como simuladas. Para producción cambia a modo REAL con credenciales del SIN.
  </p>
  <form method="POST" action="{{ route('configuracion.siat') }}" enctype="multipart/form-data">
    @csrf
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
      <div><label class="form-label-giseca">Modo *</label><select name="siat_modo" class="form-control-giseca">
          <option value="simulador" {{ $siat['modo'] === 'simulador' ? 'selected' : '' }}>Simulador</option>
          <option value="real" {{ $siat['modo'] === 'real' ? 'selected' : '' }}>Real (SIN)</option>
        </select></div>
      <div><label class="form-label-giseca">Ambiente *</label><select name="siat_ambiente" class="form-control-giseca">
          <option value="pruebas" {{ $siat['ambiente'] === 'pruebas' ? 'selected' : '' }}>Pruebas (piloto)</option>
          <option value="produccion" {{ $siat['ambiente'] === 'produccion' ? 'selected' : '' }}>Producción</option>
        </select></div>
      <div><label class="form-label-giseca">Modalidad *</label>
        <select name="siat_modalidad" id="siat_modalidad" class="form-control-giseca" onchange="toggleCertFields()">
          <option value="computarizada" {{ $siat['modalidad'] === 'computarizada' ? 'selected' : '' }}>Computarizada en Línea &mdash; <em>Por defecto</em></option>
          <option value="electronica" {{ $siat['modalidad'] === 'electronica' ? 'selected' : '' }}>Electrónica en Línea (requiere certificado .p12)</option>
        </select>
        <small class="text-muted d-block mt-1">Computarizada: sin firma digital. Electrónica: requiere certificado y contraseña.</small>
      </div>
      <div><label class="form-label-giseca">NIT emisor *</label><input name="siat_nit" class="form-control-giseca" value="{{ old('siat_nit', $siat['nit']) }}" required></div>
      <div><label class="form-label-giseca">Razón social *</label><input name="siat_razon_social" class="form-control-giseca" value="{{ old('siat_razon_social', $siat['razon_social']) }}" required></div>
      <div><label class="form-label-giseca">Código de sistema</label><input name="siat_codigo_sistema" class="form-control-giseca" value="{{ old('siat_codigo_sistema', $siat['codigo_sistema']) }}"></div>
      <div><label class="form-label-giseca">Sucursal</label><input name="siat_sucursal" class="form-control-giseca" value="{{ old('siat_sucursal', $siat['sucursal']) }}"></div>
      <div><label class="form-label-giseca">Punto de venta</label><input name="siat_punto_venta" class="form-control-giseca" value="{{ old('siat_punto_venta', $siat['punto_venta']) }}"></div>
      <div><label class="form-label-giseca">Ciudad</label><input name="siat_ciudad" class="form-control-giseca" value="{{ old('siat_ciudad', $siat['ciudad']) }}"></div>
      <div><label class="form-label-giseca">Teléfono fiscal</label><input name="siat_telefono" class="form-control-giseca" value="{{ old('siat_telefono', $siat['telefono']) }}"></div>
      <div><label class="form-label-giseca">Dirección fiscal</label><input name="siat_direccion" class="form-control-giseca" value="{{ old('siat_direccion', $siat['direccion']) }}"></div>
      <div><label class="form-label-giseca">Leyenda</label>
        @if(!empty($siat['leyendas']))
        <select name="siat_leyenda" class="form-control-giseca">
          @foreach($siat['leyendas'] as $ley)
          <option value="{{ $ley }}" {{ old('siat_leyenda', \App\Models\Configuracion::get('siat_leyenda')) === $ley ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($ley, 80) }}</option>
          @endforeach
        </select>
        @else
        <input name="siat_leyenda" class="form-control-giseca" value="{{ old('siat_leyenda', \App\Models\Configuracion::get('siat_leyenda')) }}" placeholder="Leyenda oficial del periodo">
        @endif
      </div>
      <div><label class="form-label-giseca">CAFC (contingencia)</label><input name="siat_cafc" class="form-control-giseca" value="{{ old('siat_cafc', $siat['cafc'] ?? '') }}" placeholder="Código de contingencia"></div>
      <div id="bloque-certificado" style="display:{{ $siat['modalidad'] === 'electronica' ? 'block' : 'none' }}">
        <div><label class="form-label-giseca">Certificado .p12 (privado) &mdash; <em>Solo modalidad Electrónica</em></label><input type="file" name="siat_certificado" class="form-control-giseca" accept=".p12,.pfx"></div>
        <div><label class="form-label-giseca">Contraseña certificado {{ $siat['tiene_password'] ? '(guardada)' : '' }}</label><input type="password" name="siat_cert_password" class="form-control-giseca" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;"></div>
      </div>
      <div><label class="form-label-giseca">Token Delegado {{ $siat['tiene_token'] ? '(guardado)' : '' }}</label><input type="password" name="siat_token" class="form-control-giseca" placeholder="TokenApi ..."></div>
      <script>
      function toggleCertFields() {
          var m = document.getElementById('siat_modalidad').value;
          document.getElementById('bloque-certificado').style.display = (m === 'electronica') ? 'block' : 'none';
      }
      </script>
    </div>
    <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
      <button class="btn-giseca btn-primario">Guardar SIAT</button>
  </form>
  <form method="POST" action="{{ route('configuracion.probar-siat') }}" style="display:inline;">@csrf<button class="btn-giseca btn-outline"><i class="bi bi-plug"></i> Probar conexión (CUIS + CUFD)</button></form>
  <form method="POST" action="{{ route('configuracion.sincronizar') }}" style="display:inline;">@csrf<button class="btn-giseca btn-outline"><i class="bi bi-arrow-repeat"></i> Sincronizar leyendas</button></form>
</div>
</div>

@if($eventos->count())
<div class="card-giseca" style="margin-top:18px; padding:0; overflow:hidden;">
  <div style="padding:14px 18px 0;">
    <h6 style="margin:0;">Últimas comunicaciones con el SIN</h6>
  </div>
  <table class="tabla-giseca">
    <thead>
      <tr>
        <th>Método</th>
        <th>Fecha</th>
        <th>Resultado</th>
      </tr>
    </thead>
    <tbody>
      @foreach($eventos as $ev)
      <tr>
        <td><span class="codigo-chip">{{ $ev->metodo }}</span></td>
        <td>{{ $ev->fecha->format('Y-m-d H:i') }}</td>
        <td><span class="estado {{ $ev->exitoso ? 'estado-aprobada' : 'estado-rechazada' }}">{{ $ev->exitoso ? 'OK' : 'FALLO' }}</span></td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection

@push('scripts')
<script>
  const dropzone = document.getElementById('dropzone');
  ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, e => {
    e.preventDefault();
    dropzone.classList.add('dragover');
  }));
  ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, e => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
  }));
  dropzone.addEventListener('drop', e => {
    if (e.dataTransfer.files[0]) {
      document.getElementById('inputMembrete').files = e.dataTransfer.files;
      document.getElementById('formMembrete').submit();
    }
  });

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
  @if(session('exito')) mostrarToast(@json(session('exito')), 'exito');
  @endif
  @if(session('error')) mostrarToast(@json(session('error')), 'error');
  @endif
</script>
@endpush