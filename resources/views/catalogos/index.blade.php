@extends('layouts.app')

@section('title', 'Catálogos Oficiales SIN')

@section('content')
<div class="card-giseca" style="margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <div style="display:flex; align-items:center; gap:8px;">
        <h5 style="margin:0; font-weight:700;">Catálogos y Tablas Paramétricas SIN</h5>
        <span class="codigo-chip" style="background:var(--gc-primario-suave); color:var(--gc-primario); font-weight:600;">
          {{ $totalGeneral }} registros cargados
        </span>
      </div>
      <div style="font-size:12.5px; color:var(--gc-gris); margin-top:4px;">
        Normativa RND 102100000011 (SIAT en línea). Utilizados en homologación de productos, emisión y contingencia.
      </div>
    </div>
    <form method="POST" action="{{ route('catalogos.sincronizar') }}" style="margin:0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      @csrf
      <select name="tipo" id="selectorTipoSync" class="form-control-giseca" style="width:230px;">
        <option value="">Todos los catálogos</option>
        @foreach(\App\Models\CatalogoSin::TIPOS as $t => $et)
          <option value="{{ $t }}">{{ $et }} ({{ $grupos[$t]['count'] ?? 0 }})</option>
        @endforeach
      </select>
      <button type="submit" class="btn-giseca btn-primario" id="btnSync">
        <i class="bi bi-arrow-repeat"></i> Sincronizar con SIN
      </button>
    </form>
  </div>
</div>

{{-- Tabs de navegación de catálogos --}}
<div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:6px; margin-bottom:14px; border-bottom:1px solid var(--gc-borde);">
  @php $i = 0; @endphp
  @foreach($grupos as $tipo => $g)
    <button type="button" 
            class="btn-giseca btn-tab {{ $i === 0 ? 'btn-tab-activo' : 'btn-tab-inactivo' }}" 
            data-tab="{{ $tipo }}"
            onclick="cambiarPestana('{{ $tipo }}')"
            style="white-space:nowrap; padding:7px 14px; font-size:13px; display:inline-flex; align-items:center; gap:6px; border-radius:6px;">
      <span>{{ $g['etiqueta'] }}</span>
      <span class="codigo-chip" style="font-size:11px; padding:2px 6px;">{{ $g['count'] }}</span>
    </button>
    @php $i++; @endphp
  @endforeach
</div>

{{-- Barra de búsqueda rápida --}}
<div class="card-giseca" style="margin-bottom:14px; padding:10px 14px;">
  <div style="position:relative;">
    <i class="bi bi-search" style="position:absolute; left:12px; top:10px; color:var(--gc-gris); font-size:13px;"></i>
    <input type="text" 
           id="filtroCatalogo" 
           class="form-control-giseca" 
           placeholder="Filtrar por código o descripción en el catálogo activo..." 
           style="padding-left:34px; font-size:13px;"
           oninput="filtrarTablaActiva()">
  </div>
</div>

{{-- Tablas de cada catálogo --}}
@php $j = 0; @endphp
@foreach($grupos as $tipo => $g)
  <div class="card-giseca panel-catalogo" id="panel_{{ $tipo }}" style="padding:0; overflow:hidden; {{ $j === 0 ? '' : 'display:none;' }}">
    <div style="padding:12px 18px; display:flex; justify-content:space-between; align-items:center; background:var(--gc-superficie); border-bottom:1px solid var(--gc-borde);">
      <div>
        <strong style="font-size:14px;">{{ $g['etiqueta'] }}</strong>
        <span style="font-size:12px; color:var(--gc-gris); margin-left:8px;" id="contador_{{ $tipo }}">{{ $g['count'] }} registros</span>
      </div>
      <form method="POST" action="{{ route('catalogos.sincronizar') }}" style="margin:0;">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <button type="submit" class="btn-giseca btn-outline btn-sm">
          <i class="bi bi-arrow-repeat"></i> Sincronizar solo este
        </button>
      </form>
    </div>

    <div style="max-height:540px; overflow-y:auto;">
      <table class="tabla-giseca" id="tabla_{{ $tipo }}">
        <thead>
          <tr>
            <th style="width:140px;">Código SIN</th>
            <th>Descripción Oficial</th>
            <th style="width:90px; text-align:right;">Acción</th>
          </tr>
        </thead>
        <tbody>
          @forelse($g['items'] as $it)
            <tr data-codigo="{{ strtolower($it->codigo) }}" data-desc="{{ strtolower($it->descripcion) }}">
              <td>
                <span class="codigo-chip" style="font-weight:700;">{{ $it->codigo }}</span>
              </td>
              <td style="font-size:13px;">
                {{ $it->descripcion }}
                @if($it->extra)
                  <div style="font-size:11px; color:var(--gc-gris); margin-top:2px;">{{ $it->extra }}</div>
                @endif
              </td>
              <td style="text-align:right;">
                <button type="button" class="btn-giseca btn-outline btn-sm" onclick="copiarCodigo('{{ $it->codigo }}')" title="Copiar código">
                  <i class="bi bi-copy"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" style="text-align:center; padding:35px; color:var(--gc-gris-claro);">
                <i class="bi bi-inbox" style="font-size:26px; display:block; margin-bottom:6px; opacity:.5;"></i>
                Catálogo vacío. Pulsa el botón Sincronizar para cargarlo.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @php $j++; @endphp
@endforeach

<style>
.btn-tab {
  background: transparent;
  border: 1px solid transparent;
  color: var(--gc-gris);
  cursor: pointer;
  transition: all .15s ease;
}
.btn-tab:hover {
  background: var(--gc-superficie);
  color: var(--gc-texto);
}
.btn-tab-activo {
  background: var(--gc-primario-suave) !important;
  color: var(--gc-primario-oscuro) !important;
  border: 1px solid var(--gc-primario) !important;
  font-weight: 700;
}
.btn-tab-inactivo {
  background: var(--gc-superficie);
  border: 1px solid var(--gc-borde);
}
</style>
@endsection

@push('scripts')
<script>
let pestanaActiva = '{{ array_key_first($grupos) }}';

function cambiarPestana(tipo) {
  pestanaActiva = tipo;
  document.querySelectorAll('.panel-catalogo').forEach(p => p.style.display = 'none');
  const panel = document.getElementById('panel_' + tipo);
  if (panel) panel.style.display = 'block';

  document.querySelectorAll('.btn-tab').forEach(b => {
    if (b.dataset.tab === tipo) {
      b.classList.remove('btn-tab-inactivo');
      b.classList.add('btn-tab-activo');
    } else {
      b.classList.remove('btn-tab-activo');
      b.classList.add('btn-tab-inactivo');
    }
  });

  const sel = document.getElementById('selectorTipoSync');
  if (sel) sel.value = tipo;

  filtrarTablaActiva();
}

function filtrarTablaActiva() {
  const query = (document.getElementById('filtroCatalogo').value || '').toLowerCase().trim();
  const tabla = document.getElementById('tabla_' + pestanaActiva);
  if (!tabla) return;

  const filas = tabla.querySelectorAll('tbody tr[data-codigo]');
  let visibles = 0;

  filas.forEach(tr => {
    const cod = tr.getAttribute('data-codigo') || '';
    const desc = tr.getAttribute('data-desc') || '';
    const match = !query || cod.includes(query) || desc.includes(query);
    tr.style.display = match ? '' : 'none';
    if (match) visibles++;
  });

  const contador = document.getElementById('contador_' + pestanaActiva);
  if (contador) {
    contador.textContent = query ? `${visibles} de ${filas.length} encontrados` : `${filas.length} registros`;
  }
}

function copiarCodigo(cod) {
  navigator.clipboard.writeText(cod).then(() => {
    mostrarToast('Código ' + cod + ' copiado al portapapeles', 'exito');
  }).catch(() => {
    mostrarToast('Código: ' + cod, 'info');
  });
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
