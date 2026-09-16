/* Genera el sidebar/topbar corporativos y aplica el guardián de sesión */
function renderLayout(paginaActiva, tituloPagina) {
  exigirSesion();

  const temaGuardado = localStorage.getItem('giseca_tema') || 'light';
  document.documentElement.setAttribute('data-bs-theme', temaGuardado);

  const enlaces = [
    { href: 'dashboard.html', icono: 'speedometer2', texto: 'Dashboard', id: 'dashboard' },
    { href: 'inventario.html', icono: 'box-seam', texto: 'Inventario', id: 'inventario' },
    { href: 'proformas-listar.html', icono: 'file-earmark-text', texto: 'Proformas', id: 'proformas' },
    { href: 'clientes.html', icono: 'people', texto: 'Clientes', id: 'clientes' },
    { href: 'compras.html', icono: 'cart-plus', texto: 'Compras', id: 'compras' },
    { href: 'ventas.html', icono: 'cash-coin', texto: 'Ventas', id: 'ventas' },
    { href: 'reportes.html', icono: 'bar-chart-line', texto: 'Reportes', id: 'reportes' },
    { href: 'tributario.html', icono: 'receipt', texto: 'Tributario', id: 'tributario' },
    { href: 'comprobantes.html', icono: 'receipt-cutoff', texto: 'Comprobantes', id: 'comprobantes' },
  ];
  const navHtml = enlaces.map(e =>
    `<a href="${e.href}" class="${paginaActiva === e.id ? 'active' : ''}"><i class="bi bi-${e.icono}"></i> ${e.texto}</a>`
  ).join('\n');

  document.body.insertAdjacentHTML('afterbegin', `
    <div class="gc-sidebar-overlay" id="gcSidebarOverlay" onclick="gcCerrarSidebar()"></div>
    <div class="gc-shell">
      <aside class="gc-sidebar" id="gcSidebar">
        <div class="gc-brand">
          <div class="logo-fallback">G</div>
          <div><div class="name">GISECA</div><div class="sub">ERP Comercial</div></div>
        </div>
        <nav>
          ${navHtml}
          <div class="seccion">Administración</div>
          <a href="configuracion.html" class="${paginaActiva === 'configuracion' ? 'active' : ''}"><i class="bi bi-gear"></i> Configuración</a>
        </nav>
        <div class="gc-userbox">
          <div class="avatar">A</div>
          <div><div style="font-weight:600;">Administrador GISECA</div><div style="color:var(--gc-gris-claro);">Modo local</div></div>
        </div>
      </aside>

      <div class="gc-main">
        <div class="gc-topbar">
          <button class="gc-menu-toggle btn btn-sm btn-outline-secondary" onclick="gcAbrirSidebar()"><i class="bi bi-list"></i></button>
          <h1>${tituloPagina}</h1>
          <div class="gc-search">
            <i class="bi bi-search"></i>
            <input type="text" id="gcBuscadorGlobal" placeholder="Buscar productos, clientes, proformas...">
            <div class="gc-search-results" id="gcResultadosBusqueda"></div>
          </div>
          <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
            <button class="gc-theme-toggle" onclick="gcAlternarTema()" title="Cambiar modo claro/oscuro">
              <i class="bi bi-moon-stars" id="gcIconoTema"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="cerrarSesionYSalir()"><i class="bi bi-box-arrow-right"></i></button>
          </div>
        </div>
        <div class="gc-content" id="gcContenidoPlaceholder"></div>
      </div>
    </div>
  `);

  const contenidoOriginal = document.getElementById('contenidoPagina');
  if (contenidoOriginal) {
    document.getElementById('gcContenidoPlaceholder').appendChild(contenidoOriginal);
    contenidoOriginal.style.display = 'block';
  }

  gcActualizarIconoTema();
  gcInicializarBusquedaGlobal();
}

function gcAbrirSidebar() {
  document.getElementById('gcSidebar').classList.add('abierto');
  document.getElementById('gcSidebarOverlay').classList.add('abierto');
}
function gcCerrarSidebar() {
  document.getElementById('gcSidebar').classList.remove('abierto');
  document.getElementById('gcSidebarOverlay').classList.remove('abierto');
}

function gcActualizarIconoTema() {
  const tema = document.documentElement.getAttribute('data-bs-theme');
  document.getElementById('gcIconoTema').className = tema === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
}
function gcAlternarTema() {
  const actual = document.documentElement.getAttribute('data-bs-theme');
  const nuevo = actual === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-bs-theme', nuevo);
  localStorage.setItem('giseca_tema', nuevo);
  gcActualizarIconoTema();
}

function gcInicializarBusquedaGlobal() {
  const input = document.getElementById('gcBuscadorGlobal');
  const resultados = document.getElementById('gcResultadosBusqueda');
  if (!input) return;

  input.addEventListener('input', function() {
    const termino = this.value.trim().toLowerCase();
    if (termino.length < 2) { resultados.classList.remove('mostrar'); return; }

    const productos = DB.productos().filter(p =>
      p.codigo.toLowerCase().includes(termino) || p.descripcion.toLowerCase().includes(termino) || (p.equivalente||'').toLowerCase().includes(termino)
    ).slice(0,5);
    const clientes = DB.clientes().filter(c => c.nombre.toLowerCase().includes(termino)).slice(0,5);
    const proformas = DB.proformas().filter(p => p.numero.toLowerCase().includes(termino) || p.clienteNombre.toLowerCase().includes(termino)).slice(0,5);

    let html = '';
    if (productos.length) {
      html += '<div class="grupo-titulo">Productos</div>';
      productos.forEach(p => html += `<a href="inventario.html"><i class="bi bi-box-seam me-1"></i> <strong>${p.codigo}</strong> — ${p.descripcion}</a>`);
    }
    if (clientes.length) {
      html += '<div class="grupo-titulo">Clientes</div>';
      clientes.forEach(c => html += `<a href="clientes.html"><i class="bi bi-people me-1"></i> ${c.nombre}</a>`);
    }
    if (proformas.length) {
      html += '<div class="grupo-titulo">Proformas</div>';
      proformas.forEach(p => html += `<a href="proformas-ver.html?id=${p.id}"><i class="bi bi-file-earmark-text me-1"></i> <strong>${p.numero}</strong> — ${p.clienteNombre}</a>`);
    }
    resultados.innerHTML = html || '<div class="vacio">Sin resultados.</div>';
    resultados.classList.add('mostrar');
  });

  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !resultados.contains(e.target)) resultados.classList.remove('mostrar');
  });
}

function cerrarSesionYSalir() {
  DB.cerrarSesion();
  window.location.href = 'login.html';
}
