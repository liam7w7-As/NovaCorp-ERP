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
      searchField: ['nombre', 'nit'],
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
          return '<div>' + esc(d.nombre) + (d.nit ? ' <span style="color:var(--gc-gris-claro)">(' + esc(d.nit) + ')</span>' : '') + '</div>';
        },
        item: function (d, esc) { return '<div>' + esc(d.nombre) + '</div>'; },
      },
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  gcActualizarIconoTema();
  gcInicializarBusquedaGlobal();
  gcInicializarTomSelect();

  var toggleMenu = document.getElementById('gcMenuToggle');
  if (toggleMenu && !toggleMenu.getAttribute('onclick')) {
    toggleMenu.addEventListener('click', gcAbrirSidebar);
  }
  var toggleTema = document.getElementById('gcThemeToggle');
  if (toggleTema && !toggleTema.getAttribute('onclick')) {
    toggleTema.addEventListener('click', gcAlternarTema);
  }
});
