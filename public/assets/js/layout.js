/* GISECA ERP - Layout Laravel (adaptado de gisecav1/js/layout.js)
   Provee: sidebar móvil, toggle de tema claro/oscuro, buscador global real
   (GET /buscar-global) y atajos de teclado (/ o Ctrl+K buscar, Esc cerrar).
   Compatible con IDs nuevos (#gcMenuToggle, #gcThemeToggle, #gcSearchInput)
   y legacy (#gcBuscadorGlobal) del sistema original. */

function gcAbrirSidebar() {
  var sb = document.getElementById('gcSidebar');
  var ov = document.getElementById('gcSidebarOverlay');
  if (sb) sb.classList.add('abierto');
  if (ov) ov.classList.add('abierto');
}

function gcCerrarSidebar() {
  var sb = document.getElementById('gcSidebar');
  var ov = document.getElementById('gcSidebarOverlay');
  if (sb) sb.classList.remove('abierto');
  if (ov) ov.classList.remove('abierto');
}

function gcActualizarIconoTema() {
  var tema = document.documentElement.getAttribute('data-bs-theme');
  var icono = document.getElementById('gcIconoTema');
  if (icono) icono.className = tema === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
}

function gcAlternarTema() {
  var actual = document.documentElement.getAttribute('data-bs-theme');
  var nuevo = actual === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-bs-theme', nuevo);
  try { localStorage.setItem('giseca_tema', nuevo); } catch (e) {}
  gcActualizarIconoTema();
}

/* Compatibilidad: si alguna vista legacy llama a renderLayout(), no duplicar el layout Blade,
   solo re-aplicar tema e inicializar buscador. */
function renderLayout() {
  gcActualizarIconoTema();
  gcInicializarBusquedaGlobal();
}

function gcEscaparHtml(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function gcInicializarBusquedaGlobal() {
  var input = document.getElementById('gcSearchInput') || document.getElementById('gcBuscadorGlobal');
  var resultados = document.getElementById('gcResultadosBusqueda') || document.getElementById('gcSearchResults');
  if (!input || !resultados) return;
  if (input.dataset.gcInit === '1') return;
  input.dataset.gcInit = '1';

  var timer = null;
  input.addEventListener('input', function () {
    var termino = this.value.trim();
    clearTimeout(timer);
    if (termino.length < 2) { resultados.classList.remove('mostrar'); resultados.innerHTML = ''; return; }
    timer = setTimeout(function () {
      fetch('/buscar-global?q=' + encodeURIComponent(termino), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (grupos) {
          if (!grupos.length) {
            resultados.innerHTML = '<div class="vacio">Sin resultados.</div>';
          } else {
            var html = '';
            grupos.forEach(function (g) {
              html += '<div class="grupo-titulo">' + gcEscaparHtml(g.titulo) + '</div>';
              g.items.forEach(function (it) {
                html += '<a href="' + gcEscaparHtml(it.url) + '">' + gcEscaparHtml(it.texto) + '</a>';
              });
            });
            resultados.innerHTML = html;
          }
          resultados.classList.add('mostrar');
        })
        .catch(function () {
          resultados.innerHTML = '<div class="vacio">Error buscando.</div>';
          resultados.classList.add('mostrar');
        });
    }, 250);
  });

  document.addEventListener('click', function (e) {
    if (!input.contains(e.target) && !resultados.contains(e.target)) resultados.classList.remove('mostrar');
  });

  // Atajos: / o Ctrl+K enfocan el buscador, Esc lo limpia y cierra modales
  document.addEventListener('keydown', function (e) {
    var escribiendo = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement && document.activeElement.tagName);
    if ((e.key === '/' && !escribiendo) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
      e.preventDefault();
      input.focus();
    } else if (e.key === 'Escape') {
      if (document.activeElement === input) {
        input.value = '';
        resultados.classList.remove('mostrar');
        input.blur();
      }
      document.querySelectorAll('.modal-giseca.abierto').forEach(function (m) { m.classList.remove('abierto'); });
      gcCerrarSidebar();
    }
  });
}

/* Buscadores inteligentes: <select data-tomselect="URL_JSON"> se convierte en
   combobox con búsqueda remota. Sin CDN (offline) se conserva el select nativo. */
function gcInicializarTomSelect() {
  if (typeof TomSelect === 'undefined') return;
  document.querySelectorAll('select[data-tomselect]').forEach(function (sel) {
    if (sel.tomselect) return;
    new TomSelect(sel, {
      valueField: 'id',
      labelField: 'nombre',
      searchField: ['nombre', 'nit', 'telefono'],
      placeholder: 'Escribe para buscar...',
      maxOptions: 15,
      load: function (q, cb) {
        if (!q || q.length < 2) return cb();
        fetch(sel.getAttribute('data-tomselect') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { cb(d); })
          .catch(function () { cb(); });
      },
      render: {
        option: function (d, esc) {
          var linea = '<div><strong>' + esc(d.nombre) + '</strong>' + (d.nit ? ' <span style="color:var(--gc-gris-claro)">NIT ' + esc(d.nit) + '</span>' : '');
          var detalles = [];
          if (d.telefono) detalles.push('Tel: ' + esc(d.telefono));
          if (d.correo) detalles.push(esc(d.correo));
          if (d.direccion) detalles.push(esc(d.direccion));
          if (detalles.length) linea += '<br><small style="color:var(--gc-gris-claro)">' + detalles.join(' · ') + '</small>';
          return linea + '</div>';
        },
        item: function (d, esc) {
          var extras = [];
          if (d.nit) extras.push('NIT/CI ' + esc(d.nit));
          if (d.telefono) extras.push('Tel: ' + esc(d.telefono));
          return '<div>' + esc(d.nombre) + (extras.length ? ' <small style="color:var(--gc-gris-claro)">(' + extras.join(' · ') + ')</small>' : '') + '</div>';
        },
      },
    });
  });
}

var gcDialogResolver = null;
var gcDialogFocoAnterior = null;

function gcCerrarDialogo(resultado) {
  var modal = document.getElementById('gcDialog');
  if (!modal || !modal.classList.contains('abierto')) return;
  modal.classList.remove('abierto');
  document.body.style.overflow = '';
  if (gcDialogFocoAnterior && typeof gcDialogFocoAnterior.focus === 'function') gcDialogFocoAnterior.focus();
  if (gcDialogResolver) gcDialogResolver(resultado);
  gcDialogResolver = null;
}

function gcAbrirDialogo(mensaje, opciones) {
  opciones = opciones || {};
  var modal = document.getElementById('gcDialog');
  var titulo = document.getElementById('gcDialogTitle');
  var texto = document.getElementById('gcDialogMessage');
  var icono = document.getElementById('gcDialogIcon');
  var cancelar = document.getElementById('gcDialogCancel');
  var confirmar = document.getElementById('gcDialogConfirm');
  var soloAviso = opciones.soloAviso === true;
  var variante = opciones.variante || 'advertencia';

  gcDialogFocoAnterior = document.activeElement;
  titulo.textContent = opciones.titulo || (soloAviso ? 'Revisa la información' : 'Confirmar acción');
  texto.textContent = mensaje;
  cancelar.style.display = soloAviso ? 'none' : '';
  cancelar.textContent = opciones.cancelar || 'Cancelar';
  confirmar.textContent = opciones.confirmar || (soloAviso ? 'Entendido' : 'Confirmar');
  confirmar.className = 'btn-giseca ' + (variante === 'peligro' ? 'btn-peligro' : 'btn-primario');
  icono.className = 'gc-dialog-icon ' + variante;
  icono.innerHTML = variante === 'peligro'
    ? '<i class="bi bi-exclamation-triangle"></i>'
    : (soloAviso ? '<i class="bi bi-info-lg"></i>' : '<i class="bi bi-question-lg"></i>');
  modal.classList.add('abierto');
  document.body.style.overflow = 'hidden';
  setTimeout(function () { confirmar.focus(); }, 0);

  return new Promise(function (resolve) { gcDialogResolver = resolve; });
}

window.GisecaDialog = {
  confirm: function (mensaje, opciones) { return gcAbrirDialogo(mensaje, opciones); },
  alert: function (mensaje, opciones) {
    opciones = opciones || {};
    opciones.soloAviso = true;
    return gcAbrirDialogo(mensaje, opciones);
  }
};

function gcInicializarDialogos() {
  var modal = document.getElementById('gcDialog');
  var cancelar = document.getElementById('gcDialogCancel');
  var confirmar = document.getElementById('gcDialogConfirm');
  if (!modal || !cancelar || !confirmar) return;

  cancelar.addEventListener('click', function () { gcCerrarDialogo(false); });
  confirmar.addEventListener('click', function () { gcCerrarDialogo(true); });
  modal.addEventListener('click', function (e) {
    if (e.target === modal) gcCerrarDialogo(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('abierto')) {
      e.preventDefault();
      gcCerrarDialogo(false);
    }
  });

  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var form = e.target;
    var boton = e.submitter;
    var mensaje = (boton && boton.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (!mensaje || form.dataset.gcConfirmado === '1') return;

    e.preventDefault();
    var fuente = boton && boton.hasAttribute('data-confirm') ? boton : form;
    GisecaDialog.confirm(mensaje, {
      titulo: fuente.getAttribute('data-confirm-title') || 'Confirmar acción',
      confirmar: fuente.getAttribute('data-confirm-label') || 'Confirmar',
      variante: fuente.getAttribute('data-confirm-variant') || 'advertencia'
    }).then(function (aceptado) {
      if (!aceptado) return;
      form.dataset.gcConfirmado = '1';
      form.requestSubmit(boton || undefined);
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  gcActualizarIconoTema();
  gcInicializarBusquedaGlobal();
  gcInicializarTomSelect();
  gcInicializarDialogos();

  var toggleMenu = document.getElementById('gcMenuToggle');
  if (toggleMenu && !toggleMenu.getAttribute('onclick')) {
    toggleMenu.addEventListener('click', gcAbrirSidebar);
  }
  var toggleTema = document.getElementById('gcThemeToggle');
  if (toggleTema && !toggleTema.getAttribute('onclick')) {
    toggleTema.addEventListener('click', gcAlternarTema);
  }
});
