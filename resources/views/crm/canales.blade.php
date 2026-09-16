@extends('layouts.app')

@section('title', 'Líneas comerciales')

@section('content')
<div class="canales-cabecera">
  <div class="canales-nav">
    <a href="{{ route('crm.index') }}" class="btn-giseca btn-outline"><i class="bi bi-arrow-left"></i> CRM</a>
    <a href="{{ route('crm.diagnostico') }}" class="btn-giseca btn-outline"><i class="bi bi-activity"></i> Diagnóstico</a>
  </div>
  <button type="button" class="btn-giseca btn-primario" onclick="abrirCanal()"><i class="bi bi-plus-lg"></i> Nueva línea</button>
</div>

@if($errors->any())<div class="canales-alerta">{{ $errors->first() }}</div>@endif

<div class="canales-webhook">
  <div>
    <span class="webhook-etiqueta"><i class="bi bi-broadcast"></i> Webhook Meta</span>
    <strong>{{ $webhookUrl }}</strong>
    <small>Callback URL para Meta Developers. La verificación usa el mismo endpoint por GET.</small>
  </div>
  <div class="webhook-acciones">
    <button type="button" class="btn-giseca btn-outline btn-sm" onclick="copiarTexto(@json($webhookUrl))"><i class="bi bi-copy"></i> Copiar URL</button>
    <button type="button" class="btn-giseca btn-outline btn-sm" onclick="copiarTexto(@json($webhookVerifyUrl))"><i class="bi bi-shield-check"></i> Verificación</button>
  </div>
  <div class="webhook-checks">
    <span class="meta-chip {{ $webhookVerifyTokenConfigurado ? 'listo' : 'error' }}">{{ $webhookVerifyTokenConfigurado ? 'Verify token OK' : 'Sin verify token' }}</span>
    <span class="meta-chip {{ $appSecretConfigurado ? 'listo' : '' }}">{{ $appSecretConfigurado ? 'Firma activa' : 'Firma opcional' }}</span>
    <span class="meta-chip {{ $tokenGlobalConfigurado ? 'listo' : '' }}">{{ $tokenGlobalConfigurado ? 'Token global' : 'Token por línea' }}</span>
  </div>
</div>

<div class="card-giseca canales-tabla">
  <div class="canales-scroll">
    <table class="tabla-giseca">
      <thead><tr><th>Línea</th><th>Número</th><th>Ciudad</th><th>Credenciales</th><th>Validación Meta</th><th>Estado</th><th>Vendedores</th><th></th></tr></thead>
      <tbody>
        @forelse($canales as $canal)
          @php
            $tieneToken = filled($canal->access_token) || $tokenGlobalConfigurado;
            $credencialesCompletas = filled($canal->phone_number_id) && $tieneToken;
            $datosCanal = [
              'id' => $canal->id,
              'nombre' => $canal->nombre,
              'telefono' => $canal->telefono,
              'ciudad' => $canal->ciudad,
              'waba_id' => $canal->waba_id,
              'phone_number_id' => $canal->phone_number_id,
              'graph_version' => $canal->graph_version,
              'activo' => $canal->activo,
              'vendedores' => $canal->vendedores->pluck('id'),
            ];
          @endphp
          <tr data-canal-row="{{ $canal->id }}">
            <td><strong>{{ $canal->nombre }}</strong></td>
            <td><span class="codigo-chip">{{ $canal->telefono }}</span></td>
            <td>{{ $canal->ciudad ?: '-' }}</td>
            <td>
              <div class="canales-meta-stack">
                <span class="meta-chip {{ $canal->waba_id ? 'listo' : '' }}">WABA {{ $canal->waba_id ? 'OK' : 'pendiente' }}</span>
                <span class="meta-chip {{ $canal->phone_number_id ? 'listo' : '' }}">Phone ID {{ $canal->phone_number_id ? 'OK' : 'pendiente' }}</span>
                <span class="meta-chip {{ $tieneToken ? 'listo' : 'error' }}">{{ filled($canal->access_token) ? 'Token propio' : ($tokenGlobalConfigurado ? 'Token global' : 'Sin token') }}</span>
                <small>{{ $canal->graph_version ?: config('services.meta_whatsapp.graph_version', 'v21.0') }}</small>
              </div>
            </td>
            <td>
              <div class="canales-meta-stack">
                <strong data-meta-name>{{ $canal->meta_verified_name ?: 'Sin validar' }}</strong>
                <span data-meta-phone>{{ $canal->meta_display_phone_number ?: $canal->phone_number_id ?: '-' }}</span>
                <small data-meta-quality>Calidad: {{ $canal->meta_quality_rating ?: '-' }}</small>
                <small data-meta-date>{{ $canal->meta_verificado_at ? 'Validado '.$canal->meta_verificado_at->format('d/m/Y H:i') : 'Aún no probado' }}</small>
              </div>
            </td>
            <td><span data-meta-status class="estado {{ $canal->activo && $canal->estado === 'conectado' ? 'estado-enviada' : ($canal->estado === 'error' ? 'estado-anulada' : 'estado-borrador') }}">{{ $canal->activo ? ucfirst($canal->estado) : 'Inactiva' }}</span>@if($canal->ultimo_error)<small data-meta-error class="canal-error">{{ Str::limit($canal->ultimo_error, 90) }}</small>@else<small data-meta-error class="canal-error" hidden></small>@endif</td>
            <td>{{ $canal->vendedores->pluck('name')->join(', ') ?: 'Sin vendedores' }}</td>
            <td class="canales-botones"><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" title="Probar conexión Meta" data-test-url="{{ route('crm.canales.probar-meta', $canal) }}" onclick="probarCanalMeta(this)" @disabled(! $credencialesCompletas)><i class="bi bi-plug"></i></button><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" title="Editar línea" data-canal='@json($datosCanal)' onclick="editarCanal(JSON.parse(this.dataset.canal))"><i class="bi bi-pencil"></i></button></td>
          </tr>
        @empty
          <tr><td colspan="8" class="canales-vacio"><i class="bi bi-whatsapp"></i> Todavía no hay líneas comerciales registradas.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal-giseca" id="modalCanal">
  <div class="modal-box">
    <div class="canales-modal-cabecera"><h6 id="tituloCanal">Nueva línea comercial</h6><button type="button" class="btn-giseca btn-outline btn-icon" title="Cerrar" onclick="cerrarCanal()"><i class="bi bi-x-lg"></i></button></div>
    <form id="formCanal" method="POST" action="{{ route('crm.canales.store') }}">
      @csrf
      <div id="metodoCanal"></div>
      <div class="canales-form">
        <div><label class="form-label-giseca">Nombre *</label><input class="form-control-giseca" id="canal_nombre" name="nombre" placeholder="Ej. Ventas La Paz" required></div>
        <div><label class="form-label-giseca">Número de WhatsApp *</label><input class="form-control-giseca" id="canal_telefono" name="telefono" placeholder="+591 70000000" required></div>
        <div><label class="form-label-giseca">Ciudad</label><input class="form-control-giseca" id="canal_ciudad" name="ciudad"></div>
        <div><label class="form-label-giseca">WABA ID</label><input class="form-control-giseca" id="canal_waba_id" name="waba_id" autocomplete="off"></div>
        <div><label class="form-label-giseca">Phone Number ID</label><input class="form-control-giseca" id="canal_phone_number_id" name="phone_number_id" autocomplete="off"></div>
        <div><label class="form-label-giseca">Versión Graph</label><input class="form-control-giseca" id="canal_graph_version" name="graph_version" placeholder="v21.0"></div>
        <div><label class="form-label-giseca">Token de acceso</label><input type="password" class="form-control-giseca" id="canal_access_token" name="access_token" autocomplete="new-password" placeholder="Dejar vacío para conservar"></div>
        <label class="canales-check"><input type="hidden" name="activo" value="0"><input type="checkbox" id="canal_activo" name="activo" value="1" checked> Línea activa</label>
        <fieldset><legend>Vendedores asignados</legend><div class="canales-vendedores">@foreach($vendedores as $vendedor)<label><input type="checkbox" name="vendedores[]" value="{{ $vendedor->id }}" data-vendedor> <span>{{ $vendedor->name }}</span></label>@endforeach</div></fieldset>
      </div>
      <div class="canales-acciones"><button class="btn-giseca btn-primario"><i class="bi bi-check-lg"></i> Guardar</button><button type="button" class="btn-giseca btn-outline" onclick="cerrarCanal()">Cancelar</button></div>
    </form>
  </div>
</div>
@endsection

@push('styles')
<style>
.canales-cabecera{display:flex;justify-content:space-between;gap:10px;margin-bottom:16px}.canales-nav{display:flex;gap:8px;flex-wrap:wrap}.canales-alerta{background:var(--gc-rojo-suave);color:var(--gc-rojo);padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px}.canales-webhook{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px 14px;align-items:center;background:var(--gc-superficie);border:1px solid var(--gc-borde);border-radius:7px;padding:13px 14px;margin-bottom:14px}.canales-webhook>div:first-child{min-width:0;display:flex;flex-direction:column;gap:4px}.canales-webhook strong{font-family:'JetBrains Mono',monospace;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.canales-webhook small{font-size:11px;color:var(--gc-gris)}.webhook-etiqueta{display:inline-flex;align-items:center;gap:6px;color:var(--gc-info);font-size:11px;font-weight:800;text-transform:uppercase}.webhook-acciones{display:flex;gap:6px;flex-wrap:wrap}.webhook-checks{grid-column:1/-1;display:flex;gap:6px;flex-wrap:wrap}.canales-tabla{padding:0;overflow:hidden}.canales-scroll{overflow-x:auto}.canales-vacio{text-align:center;color:var(--gc-gris-claro)!important;padding:34px!important}.canales-vacio i{display:block;font-size:25px;margin-bottom:7px}.meta-chip{display:inline-flex;align-items:center;height:22px;border-radius:11px;background:var(--gc-amarillo-suave);color:var(--gc-amarillo);font-size:10px;font-weight:700;padding:0 8px;white-space:nowrap}.meta-chip.listo{background:var(--gc-verde-suave);color:var(--gc-verde)}.meta-chip.error{background:var(--gc-rojo-suave);color:var(--gc-rojo)}.canales-meta-stack{display:flex;flex-direction:column;align-items:flex-start;gap:4px;min-width:150px}.canales-meta-stack strong{font-size:12px}.canales-meta-stack span,.canales-meta-stack small{font-size:10.5px;color:var(--gc-gris)}.canal-error{display:block;margin-top:5px;color:var(--gc-rojo);font-size:10px;max-width:220px}.canales-botones{white-space:nowrap}.canales-botones .btn-giseca:disabled{opacity:.45;cursor:not-allowed}.canales-modal-cabecera{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.canales-form{display:grid;grid-template-columns:1fr 1fr;gap:12px}.canales-check{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gc-texto)}.canales-form fieldset{grid-column:1/-1;border:1px solid var(--gc-borde);border-radius:6px;padding:12px}.canales-form legend{font-size:12px;font-weight:600;color:var(--gc-gris);padding:0 5px}.canales-vendedores{display:grid;grid-template-columns:1fr 1fr;gap:8px}.canales-vendedores label{font-size:13px;display:flex;align-items:center;gap:7px}.canales-acciones{display:flex;gap:8px;margin-top:16px}@media(max-width:760px){.canales-cabecera{align-items:flex-start}.canales-webhook{grid-template-columns:1fr}.webhook-acciones{justify-content:flex-start}.canales-webhook strong{white-space:normal;word-break:break-all}}@media(max-width:560px){.canales-cabecera{flex-direction:column}.canales-cabecera>.btn-giseca,.canales-nav{width:100%}.canales-nav .btn-giseca{flex:1;justify-content:center}.canales-form,.canales-vendedores{grid-template-columns:1fr}.canales-check{min-height:36px}}
</style>
@endpush

@push('scripts')
<script>
const canalesCsrf=@json(csrf_token());
function abrirCanal(){document.getElementById('tituloCanal').textContent='Nueva línea comercial';document.getElementById('formCanal').action=@json(route('crm.canales.store'));document.getElementById('metodoCanal').innerHTML='';['nombre','telefono','ciudad','waba_id','phone_number_id','graph_version','access_token'].forEach(c=>document.getElementById('canal_'+c).value='');document.getElementById('canal_activo').checked=true;document.querySelectorAll('[data-vendedor]').forEach(el=>el.checked=false);document.getElementById('modalCanal').classList.add('abierto')}
function editarCanal(canal){document.getElementById('tituloCanal').textContent='Editar línea comercial';document.getElementById('formCanal').action=@json(url('/crm/canales'))+'/'+canal.id;document.getElementById('metodoCanal').innerHTML='<input type="hidden" name="_method" value="PUT">';document.getElementById('canal_nombre').value=canal.nombre||'';document.getElementById('canal_telefono').value=canal.telefono||'';document.getElementById('canal_ciudad').value=canal.ciudad||'';document.getElementById('canal_waba_id').value=canal.waba_id||'';document.getElementById('canal_phone_number_id').value=canal.phone_number_id||'';document.getElementById('canal_graph_version').value=canal.graph_version||'';document.getElementById('canal_access_token').value='';document.getElementById('canal_activo').checked=Boolean(canal.activo);document.querySelectorAll('[data-vendedor]').forEach(el=>el.checked=canal.vendedores.includes(Number(el.value)));document.getElementById('modalCanal').classList.add('abierto')}
function cerrarCanal(){document.getElementById('modalCanal').classList.remove('abierto')}
async function copiarTexto(texto){try{await navigator.clipboard.writeText(texto);mostrarToast('Copiado al portapapeles.','exito')}catch(error){mostrarToast('No se pudo copiar automáticamente.','error')}}
async function probarCanalMeta(boton){const fila=boton.closest('[data-canal-row]');boton.disabled=true;const icono=boton.querySelector('i');const claseAnterior=icono.className;icono.className='bi bi-arrow-repeat';try{const respuesta=await fetch(boton.dataset.testUrl,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':canalesCsrf}});const datos=await respuesta.json();if(!respuesta.ok)throw new Error(datos.message||'No se pudo validar la línea.');actualizarFilaMeta(fila,datos.canal);mostrarToast(datos.message,'exito')}catch(error){const errorEl=fila.querySelector('[data-meta-error]');if(errorEl){errorEl.hidden=false;errorEl.textContent=error.message}const estado=fila.querySelector('[data-meta-status]');if(estado){estado.textContent='Error';estado.className='estado estado-anulada'}mostrarToast(error.message,'error')}finally{icono.className=claseAnterior;boton.disabled=false}}
function actualizarFilaMeta(fila,canal){const estado=fila.querySelector('[data-meta-status]');if(estado){estado.textContent=capitalizar(canal.estado||'conectado');estado.className='estado estado-enviada'}const nombre=fila.querySelector('[data-meta-name]');if(nombre)nombre.textContent=canal.meta_verified_name||'Sin nombre verificado';const telefono=fila.querySelector('[data-meta-phone]');if(telefono)telefono.textContent=canal.meta_display_phone_number||'-';const calidad=fila.querySelector('[data-meta-quality]');if(calidad)calidad.textContent='Calidad: '+(canal.meta_quality_rating||'-');const fecha=fila.querySelector('[data-meta-date]');if(fecha)fecha.textContent=canal.meta_verificado_at?'Validado '+canal.meta_verificado_at:'Validado ahora';const errorEl=fila.querySelector('[data-meta-error]');if(errorEl){errorEl.hidden=true;errorEl.textContent=''}}
function capitalizar(valor){return String(valor||'').charAt(0).toUpperCase()+String(valor||'').slice(1)}
function mostrarToast(mensaje,tipo){let toast=document.getElementById('toastGiseca');if(!toast){toast=document.createElement('div');toast.id='toastGiseca';document.body.appendChild(toast)}toast.textContent=mensaje;toast.className='toast-giseca mostrar '+(tipo||'');setTimeout(()=>toast.classList.remove('mostrar'),2800)}
@if(session('exito')) mostrarToast(@json(session('exito')),'exito'); @endif
</script>
@endpush
