@extends('layouts.app')

@section('title', 'Diagnóstico CRM Meta')

@section('content')
<div class="diagnostico-cabecera">
  <div>
    <h2>Diagnóstico Meta</h2>
    <p>Estado operativo para webhooks, coexistencia y líneas comerciales.</p>
  </div>
  <div class="diagnostico-acciones">
    <a href="{{ route('crm.index') }}" class="btn-giseca btn-outline"><i class="bi bi-arrow-left"></i> CRM</a>
    <a href="{{ route('crm.canales') }}" class="btn-giseca btn-primario"><i class="bi bi-whatsapp"></i> Líneas</a>
  </div>
</div>

<div class="diagnostico-hero {{ $resumen['bloqueos'] > 0 ? 'bloqueado' : ($resumen['advertencias'] > 0 ? 'alerta' : 'listo') }}">
  <div class="diagnostico-estado">
    <span>Estado general</span>
    <strong>{{ $resumen['estado'] }}</strong>
    <small>{{ $resumen['bloqueos'] }} bloqueo(s) · {{ $resumen['advertencias'] }} alerta(s)</small>
  </div>
  <div class="diagnostico-kpis">
    <div><span>Líneas</span><strong>{{ $resumen['total_canales'] }}</strong><small>{{ $resumen['canales_activos'] }} activas</small></div>
    <div><span>Credenciales</span><strong>{{ $resumen['canales_con_credenciales'] }}</strong><small>listas</small></div>
    <div><span>Validadas</span><strong>{{ $resumen['canales_validados'] }}</strong><small>Meta OK</small></div>
    <div><span>Tokens</span><strong>{{ $tokenGlobalConfigurado ? 'Global' : 'Por línea' }}</strong><small>{{ $storagePublicoListo ? 'Storage OK' : 'Storage pendiente' }}</small></div>
  </div>
</div>

<div class="diagnostico-grid">
  <section class="card-giseca diagnostico-checklist">
    <header>
      <h6>Checklist técnico</h6>
      <span class="estado {{ $resumen['bloqueos'] > 0 ? 'estado-anulada' : 'estado-enviada' }}">{{ $resumen['bloqueos'] > 0 ? 'Acción requerida' : 'Operable' }}</span>
    </header>
    <div class="diagnostico-checks">
      @foreach($checks as $check)
        <article class="diagnostico-check {{ $check['ok'] ? 'ok' : $check['nivel'] }}">
          <i class="bi {{ $check['icono'] }}"></i>
          <div>
            <strong>{{ $check['titulo'] }}</strong>
            <span>{{ $check['detalle'] }}</span>
          </div>
        </article>
      @endforeach
    </div>
  </section>

  <section class="card-giseca diagnostico-tecnico">
    <header><h6>Endpoints y entorno</h6></header>
    <dl>
      <div><dt>APP_URL</dt><dd>{{ $appUrl }}</dd></div>
      <div><dt>Callback URL</dt><dd><span>{{ $webhookUrl }}</span><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" title="Copiar callback" onclick="copiarDiagnostico(@json($webhookUrl))"><i class="bi bi-copy"></i></button></dd></div>
      <div><dt>Verificación</dt><dd><span>{{ $webhookVerifyUrl }}</span><button type="button" class="btn-giseca btn-outline btn-icon btn-sm" title="Copiar verificación" onclick="copiarDiagnostico(@json($webhookVerifyUrl))"><i class="bi bi-shield-check"></i></button></dd></div>
      <div><dt>Storage público</dt><dd>{{ $storagePublicoListo ? 'Disponible' : 'Pendiente' }}</dd></div>
    </dl>
  </section>
</div>

<section class="card-giseca diagnostico-canales">
  <div class="diagnostico-seccion-head">
    <div>
      <h6>Estado por línea</h6>
      <span>Credenciales, vendedores, validación y actividad.</span>
    </div>
    <a href="{{ route('crm.canales') }}" class="btn-giseca btn-outline btn-sm"><i class="bi bi-sliders"></i> Configurar</a>
  </div>
  <div class="diagnostico-scroll">
    <table class="tabla-giseca">
      <thead><tr><th>Línea</th><th>Credenciales</th><th>Meta</th><th>Vendedores</th><th>Leads</th><th>Estado</th></tr></thead>
      <tbody>
        @forelse($canales as $canal)
          @php
            $tieneToken = filled($canal->access_token) || $tokenGlobalConfigurado;
            $credencialesCompletas = filled($canal->phone_number_id) && $tieneToken;
            $validado = $canal->estado === 'conectado' && filled($canal->meta_verificado_at);
            $conVendedores = $canal->vendedores->isNotEmpty();
            $listo = $canal->activo && $credencialesCompletas && $conVendedores && $validado;
          @endphp
          <tr>
            <td><strong>{{ $canal->nombre }}</strong><small>{{ $canal->ciudad ?: 'Sin ciudad' }} · {{ $canal->telefono }}</small></td>
            <td>
              <div class="diagnostico-tags">
                <span class="meta-chip {{ $canal->phone_number_id ? 'listo' : 'error' }}">Phone ID</span>
                <span class="meta-chip {{ $tieneToken ? 'listo' : 'error' }}">{{ filled($canal->access_token) ? 'Token propio' : ($tokenGlobalConfigurado ? 'Token global' : 'Sin token') }}</span>
              </div>
            </td>
            <td>
              <strong>{{ $canal->meta_verified_name ?: 'Sin validar' }}</strong>
              <small>{{ $canal->meta_quality_rating ? 'Calidad '.$canal->meta_quality_rating : 'Prueba pendiente' }}</small>
            </td>
            <td>{{ $conVendedores ? $canal->vendedores->pluck('name')->join(', ') : 'Sin vendedores' }}</td>
            <td>{{ $canal->leads_count }}</td>
            <td>
              <span class="estado {{ $listo ? 'estado-enviada' : ($canal->ultimo_error ? 'estado-anulada' : 'estado-borrador') }}">{{ $listo ? 'Listo' : ($canal->activo ? ucfirst($canal->estado) : 'Inactiva') }}</span>
              @if($canal->ultimo_error)<small class="diagnostico-error">{{ Str::limit($canal->ultimo_error, 95) }}</small>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="diagnostico-vacio"><i class="bi bi-whatsapp"></i> Sin líneas comerciales registradas.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

<section class="diagnostico-pruebas">
  <h6>Pruebas finales</h6>
  <ol>
    <li>Verificar webhook desde Meta Developers.</li>
    <li>Probar conexión de cada línea comercial.</li>
    <li>Enviar un WhatsApp entrante y confirmar creación/asignación del lead.</li>
    <li>Responder desde el chat y adjuntar una cotización de prueba.</li>
  </ol>
</section>
@endsection

@push('styles')
<style>
.diagnostico-cabecera{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.diagnostico-cabecera h2{font-size:20px;margin:0}.diagnostico-cabecera p{font-size:12px;color:var(--gc-gris);margin:3px 0 0}.diagnostico-acciones{display:flex;gap:8px;flex-wrap:wrap}.diagnostico-hero{display:grid;grid-template-columns:minmax(220px,.8fr) minmax(0,1.6fr);gap:12px;align-items:stretch;border:1px solid var(--gc-borde);border-radius:7px;padding:14px;margin-bottom:14px;background:var(--gc-superficie)}.diagnostico-hero.listo{border-color:color-mix(in srgb,var(--gc-verde) 35%,var(--gc-borde))}.diagnostico-hero.alerta{border-color:color-mix(in srgb,var(--gc-amarillo) 45%,var(--gc-borde))}.diagnostico-hero.bloqueado{border-color:color-mix(in srgb,var(--gc-rojo) 38%,var(--gc-borde))}.diagnostico-estado{display:flex;flex-direction:column;gap:4px}.diagnostico-estado span,.diagnostico-kpis span{font-size:10px;text-transform:uppercase;font-weight:800;color:var(--gc-gris-claro)}.diagnostico-estado strong{font-size:22px;color:var(--gc-texto)}.diagnostico-estado small,.diagnostico-kpis small{font-size:11px;color:var(--gc-gris)}.diagnostico-kpis{display:grid;grid-template-columns:repeat(4,minmax(110px,1fr));gap:8px}.diagnostico-kpis>div{border:1px solid var(--gc-borde);border-radius:6px;padding:10px;background:var(--gc-fondo);min-width:0}.diagnostico-kpis strong{display:block;font-size:16px;color:var(--gc-texto);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.diagnostico-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);gap:14px;margin-bottom:14px}.diagnostico-checklist,.diagnostico-tecnico,.diagnostico-canales{padding:0;overflow:hidden}.diagnostico-checklist>header,.diagnostico-tecnico>header,.diagnostico-seccion-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px;border-bottom:1px solid var(--gc-borde)}.diagnostico-checklist h6,.diagnostico-tecnico h6,.diagnostico-seccion-head h6,.diagnostico-pruebas h6{font-size:13px;margin:0}.diagnostico-checks{display:grid;gap:0}.diagnostico-check{display:grid;grid-template-columns:28px minmax(0,1fr);gap:9px;padding:12px 14px;border-bottom:1px solid var(--gc-borde)}.diagnostico-check:last-child{border-bottom:0}.diagnostico-check i{font-size:17px;margin-top:1px}.diagnostico-check.ok i{color:var(--gc-verde)}.diagnostico-check.bloqueo i{color:var(--gc-rojo)}.diagnostico-check.advertencia i{color:var(--gc-amarillo)}.diagnostico-check strong{display:block;font-size:12px}.diagnostico-check span{display:block;font-size:11px;color:var(--gc-gris);line-height:1.4}.diagnostico-tecnico dl{margin:0}.diagnostico-tecnico dl>div{display:grid;grid-template-columns:112px minmax(0,1fr);gap:10px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--gc-borde)}.diagnostico-tecnico dl>div:last-child{border-bottom:0}.diagnostico-tecnico dt{font-size:10px;text-transform:uppercase;color:var(--gc-gris-claro);font-weight:800}.diagnostico-tecnico dd{margin:0;min-width:0;font-size:11.5px;color:var(--gc-texto);display:flex;align-items:center;gap:6px}.diagnostico-tecnico dd span{font-family:'JetBrains Mono',monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.diagnostico-scroll{overflow-x:auto}.diagnostico-seccion-head span{display:block;font-size:11px;color:var(--gc-gris);margin-top:2px}.diagnostico-canales td strong{display:block;font-size:12px}.diagnostico-canales td small{display:block;font-size:10.5px;color:var(--gc-gris);margin-top:3px}.diagnostico-tags{display:flex;gap:5px;flex-wrap:wrap}.meta-chip{display:inline-flex;align-items:center;height:22px;border-radius:11px;background:var(--gc-amarillo-suave);color:var(--gc-amarillo);font-size:10px;font-weight:700;padding:0 8px;white-space:nowrap}.meta-chip.listo{background:var(--gc-verde-suave);color:var(--gc-verde)}.meta-chip.error{background:var(--gc-rojo-suave);color:var(--gc-rojo)}.diagnostico-error{color:var(--gc-rojo)!important;max-width:260px}.diagnostico-vacio{text-align:center;color:var(--gc-gris-claro)!important;padding:34px!important}.diagnostico-vacio i{display:block;font-size:25px;margin-bottom:7px}.diagnostico-pruebas{border:1px solid var(--gc-borde);border-radius:7px;padding:14px;background:var(--gc-superficie)}.diagnostico-pruebas ol{margin:10px 0 0;padding-left:20px;display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:8px}.diagnostico-pruebas li{font-size:11.5px;color:var(--gc-gris);line-height:1.35;padding-right:8px}@media(max-width:1000px){.diagnostico-hero,.diagnostico-grid{grid-template-columns:1fr}.diagnostico-kpis,.diagnostico-pruebas ol{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.gc-content{padding:14px 12px}.diagnostico-cabecera{flex-direction:column}.diagnostico-acciones{width:100%}.diagnostico-acciones .btn-giseca{flex:1;justify-content:center}.diagnostico-kpis,.diagnostico-pruebas ol{grid-template-columns:1fr}.diagnostico-tecnico dl>div{grid-template-columns:1fr;gap:4px}.diagnostico-checklist>header,.diagnostico-tecnico>header,.diagnostico-seccion-head{align-items:flex-start;flex-direction:column}.diagnostico-scroll{margin:0 -1px}.diagnostico-canales .tabla-giseca{min-width:760px}}
</style>
@endpush

@push('scripts')
<script>
async function copiarDiagnostico(texto){try{await navigator.clipboard.writeText(texto);mostrarToastDiagnostico('Copiado al portapapeles.','exito')}catch(error){mostrarToastDiagnostico('No se pudo copiar automáticamente.','error')}}
function mostrarToastDiagnostico(mensaje,tipo){let toast=document.getElementById('toastGiseca');if(!toast){toast=document.createElement('div');toast.id='toastGiseca';document.body.appendChild(toast)}toast.textContent=mensaje;toast.className='toast-giseca mostrar '+(tipo||'');setTimeout(()=>toast.classList.remove('mostrar'),2800)}
</script>
@endpush
