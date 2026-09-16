<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Panel General') - GISECA SRL ERP</title>
  <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.default.min.css">
  <style>
    /* Tom Select con estética GISECA */
    .ts-wrapper.form-control-giseca,
    .ts-wrapper .ts-control {
      border: 1px solid var(--gc-borde);
      border-radius: 6px;
      font-size: 13.5px;
      background: var(--gc-superficie);
      color: var(--gc-texto);
      font-family: 'Inter', sans-serif;
    }

    .ts-wrapper.focus .ts-control {
      border-color: var(--gc-primario);
      box-shadow: 0 0 0 3px var(--gc-primario-suave);
    }

    .ts-dropdown {
      background: var(--gc-superficie);
      border: 1px solid var(--gc-borde);
      color: var(--gc-texto);
      font-size: 13px;
    }

    .ts-dropdown .option {
      padding: 8px 12px;
    }

    .ts-dropdown .active {
      background: var(--gc-primario-suave);
      color: var(--gc-primario-oscuro);
    }

    .logo-sidebar {
      width: 85px;
      height: 45px;
      object-fit: contain;
      border-radius: 10px;
    }

    .gc-sidebar {
      scrollbar-width: thin;
      scrollbar-color: var(--gc-borde) transparent;
    }

    .gc-sidebar::-webkit-scrollbar {
      width: 4px;
    }

    .gc-sidebar::-webkit-scrollbar-thumb {
      background: var(--gc-borde);
      border-radius: 4px;
    }

    .gc-sidebar nav a i {
      width: 18px;
      text-align: center;
      font-size: 14.5px;
      flex-shrink: 0;
      opacity: 0.9;
    }

    .gc-sidebar nav a.active i {
      opacity: 1;
    }

    .gc-sidebar nav .seccion {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .75px;
      color: var(--gc-gris-claro);
      padding: 13px 12px 4px;
      font-weight: 700;
    }

    .gc-sidebar nav .seccion:first-of-type {
      padding-top: 4px;
    }
  </style>
  <script>
    (function() {
      const tema = localStorage.getItem('giseca_tema') || 'light';
      document.documentElement.setAttribute('data-bs-theme', tema);
    })();
  </script>
  @stack('styles')
</head>

<body>
  <div class="gc-sidebar-overlay" id="gcSidebarOverlay" onclick="gcCerrarSidebar()"></div>
  <div class="gc-shell">
    <aside class="gc-sidebar" id="gcSidebar">
      <div class="gc-brand">

        <img
          src="{{ $logo['url'] }}"
          class="logo-sidebar"
          alt="GISECA">

        <div>
          <div class="name">SISTEMA GISECA</div>
          <div class="sub">ERP Comercial</div>
        </div>

      </div>
      <nav>
        {{-- PRINCIPAL --}}
        @canany(['dashboard', 'reportes'])
          <div class="seccion">Principal</div>
          @can('dashboard')
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard*') ? 'active' : '' }}">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
          @endcan
          @can('reportes')
            <a href="{{ route('reportes.index') }}" class="{{ request()->routeIs('reportes.*') ? 'active' : '' }}">
              <i class="bi bi-bar-chart-line"></i> Reportes
            </a>
          @endcan
        @endcanany

        {{-- VENTAS Y COMERCIAL --}}
        @canany(['crm', 'clientes', 'proformas', 'ventas'])
          <div class="seccion">Ventas y Comercial</div>
          @can('crm')
            <a href="{{ route('crm.index') }}" class="{{ request()->routeIs('crm.*') ? 'active' : '' }}">
              <i class="bi bi-funnel"></i> CRM Leads
            </a>
          @endcan
          @can('clientes')
            <a href="{{ route('clientes.index') }}" class="{{ request()->routeIs('clientes.*') ? 'active' : '' }}">
              <i class="bi bi-people"></i> Clientes
            </a>
          @endcan
          @can('proformas')
            <a href="{{ route('proformas.index') }}" class="{{ request()->routeIs('proformas.*') ? 'active' : '' }}">
              <i class="bi bi-file-earmark-text"></i> Proformas
            </a>
          @endcan
          @can('ventas')
            <a href="{{ route('ventas.index') }}" class="{{ request()->routeIs('ventas.*') ? 'active' : '' }}">
              <i class="bi bi-cart-check"></i> Ventas
            </a>
          @endcan
        @endcanany

        {{-- FACTURACIÓN & FISCAL SIAT --}}
        @canany(['facturas.ver', 'tributario'])
          <div class="seccion">Facturación SIAT</div>
          @can('facturas.ver')
            <a href="{{ route('facturas.index') }}" class="{{ request()->routeIs('facturas.*') ? 'active' : '' }}">
              <i class="bi bi-file-earmark-check"></i> Facturación
            </a>
            <a href="{{ route('contingencias.index') }}" class="{{ request()->routeIs('contingencias.*') ? 'active' : '' }}">
              <i class="bi bi-lightning-charge"></i> Contingencias
            </a>
          @endcan
          @can('tributario')
            <a href="{{ route('tributario.index') }}" class="{{ request()->routeIs('tributario.*') ? 'active' : '' }}">
              <i class="bi bi-receipt"></i> Tributario
            </a>
          @endcan
        @endcanany

        {{-- COMPRAS E INVENTARIO --}}
        @canany(['proveedores', 'compras', 'productos'])
          <div class="seccion">Compras e Inventario</div>
          @can('proveedores')
            <a href="{{ route('proveedores.index') }}" class="{{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
              <i class="bi bi-truck"></i> Proveedores
            </a>
          @endcan
          @can('compras')
            <a href="{{ route('compras.index') }}" class="{{ request()->routeIs('compras.*') ? 'active' : '' }}">
              <i class="bi bi-cart-plus"></i> Compras
            </a>
          @endcan
          @can('productos')
            <a href="{{ route('productos.index') }}" class="{{ request()->routeIs('productos.*') ? 'active' : '' }}">
              <i class="bi bi-box-seam"></i> Inventario
            </a>
            <a href="{{ route('kardex.index') }}" class="{{ request()->routeIs('kardex.*') ? 'active' : '' }}">
              <i class="bi bi-arrow-left-right"></i> Kardex Valorado
            </a>
          @endcan
        @endcanany

        {{-- CAJA Y FINANZAS --}}
        @canany(['comprobantes', 'finanzas'])
          <div class="seccion">Caja y Finanzas</div>
          @can('comprobantes')
          <a href="{{ route('caja.index') }}" class="{{ request()->routeIs('caja.*') || request()->routeIs('cuentas.*') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i> Caja y Cuentas
          </a>
          <a href="{{ route('comprobantes.index') }}" class="{{ request()->routeIs('comprobantes.*') ? 'active' : '' }}">
            <i class="bi bi-receipt-cutoff"></i> Comprobantes
          </a>
          @endcan
          @can('finanzas')
          <a href="{{ route('finanzas.index') }}" class="{{ request()->routeIs('finanzas.*') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i> Finanzas
          </a>
          @endcan
        @endcanany

        {{-- ADMINISTRACIÓN & SISTEMA --}}
        @canany(['configuracion', 'admin'])
          <div class="seccion">Administración</div>
          @can('configuracion')
            <a href="{{ route('sucursales.index') }}" class="{{ request()->routeIs('sucursales.*') ? 'active' : '' }}">
              <i class="bi bi-shop"></i> Sucursales y POS
            </a>
            <a href="{{ route('catalogos.index') }}" class="{{ request()->routeIs('catalogos.*') ? 'active' : '' }}">
              <i class="bi bi-book"></i> Catálogos SIN
            </a>
            <a href="{{ route('configuracion.index') }}" class="{{ request()->routeIs('configuracion.*') ? 'active' : '' }}">
              <i class="bi bi-gear"></i> Configuración
            </a>
          @endcan
          @can('admin')
            <a href="{{ route('usuarios.index') }}" class="{{ request()->routeIs('usuarios.*') || request()->routeIs('roles.*') ? 'active' : '' }}">
              <i class="bi bi-people-fill"></i> Usuarios y Roles
            </a>
            <a href="{{ route('auditoria.index') }}" class="{{ request()->routeIs('auditoria.*') ? 'active' : '' }}">
              <i class="bi bi-clock-history"></i> Auditoría
            </a>
            <a href="{{ route('respaldos.index') }}" class="{{ request()->routeIs('respaldos.*') ? 'active' : '' }}">
              <i class="bi bi-hdd"></i> Respaldos
            </a>
            <a href="{{ route('papelera.index') }}" class="{{ request()->routeIs('papelera.*') ? 'active' : '' }}">
              <i class="bi bi-trash"></i> Papelera
            </a>
          @endcan
        @endcanany
      </nav>
      <div class="gc-userbox">
        <div class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
        <div>
          <div style="font-weight:600;">{{ auth()->user()->name ?? 'Administrador GISECA' }}</div>
          <div style="color:var(--gc-gris-claro); text-transform:capitalize;">{{ auth()->user()->rol ?? 'admin' }}</div>
        </div>
      </div>
    </aside>

    <div class="gc-main">
      <div class="gc-topbar">
        <button class="gc-menu-toggle btn btn-sm btn-outline-secondary" id="gcMenuToggle" onclick="gcAbrirSidebar()" title="Abrir menú"><i class="bi bi-list"></i></button>
        <h1>@yield('title', 'Panel General')</h1>
        <div class="gc-search">
          <i class="bi bi-search"></i>
          <input type="text" id="gcSearchInput" placeholder="Buscar productos, clientes, proformas...">
          <div class="gc-search-results" id="gcResultadosBusqueda"></div>
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
          @auth
          @php
          $sucActual = \App\Services\SucursalContext::sucursal();
          $posActual = \App\Services\SucursalContext::puntoVenta();
          @endphp
          <a href="{{ route('sucursales.index') }}" class="codigo-chip" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px; font-weight:600; padding:5px 10px; font-size:12px; background:var(--gc-superficie); border:1px solid var(--gc-borde); color:var(--gc-texto);" title="Sucursal y POS activo para ventas y facturación. Clic para cambiar.">
            <i class="bi bi-shop" style="color:var(--gc-primario);"></i>
            <span>{{ $sucActual->nombre }}</span>
            <span style="opacity:.5;">·</span>
            <span>{{ $posActual->nombre }}</span>
          </a>
          @endauth
          <button class="gc-theme-toggle" id="gcThemeToggle" onclick="gcAlternarTema()" title="Cambiar modo claro/oscuro">
            <i class="bi bi-moon-stars" id="gcIconoTema"></i>
          </button>
          <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></button>
          </form>
        </div>
      </div>
      <div class="gc-content">
        @yield('content')
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <script src="{{ asset('assets/js/layout.js') }}"></script>
  @stack('scripts')
</body>

</html>
