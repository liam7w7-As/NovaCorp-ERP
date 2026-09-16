<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusquedaGlobalController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventoContingenciaController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\FinanzaController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\PapeleraController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TributarioController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permiso:dashboard');
    Route::get('/dashboard/exportar', [DashboardController::class, 'exportar'])->name('dashboard.exportar')->middleware('permiso:dashboard');
    Route::get('/buscar-global', [BusquedaGlobalController::class, 'buscar'])->name('buscar.global');

    // Productos / Inventario (eliminar: solo admin)
    Route::middleware('permiso:productos')->group(function () {
        Route::get('/productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
        Route::get('/productos/{producto}/codigo-barra', [ProductoController::class, 'codigoBarra'])->name('productos.barra');
        Route::post('/productos/importar', [ProductoController::class, 'importar'])->name('productos.importar');
        Route::resource('productos', ProductoController::class)->except(['show', 'create', 'edit', 'destroy']);
    });
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy')->middleware('permiso:admin');

    // Clientes (eliminar: solo admin)
    Route::middleware('permiso:clientes')->group(function () {
        Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
        Route::post('/clientes/importar', [ClienteController::class, 'importar'])->name('clientes.importar');
        Route::resource('clientes', ClienteController::class)->only(['index', 'store', 'update']);
    });
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy')->middleware('permiso:admin');

    // Proveedores (eliminar: solo admin)
    Route::middleware('permiso:proveedores')->group(function () {
        Route::get('/proveedores/buscar', [ProveedorController::class, 'buscar'])->name('proveedores.buscar');
        Route::post('/proveedores/importar', [ProveedorController::class, 'importar'])->name('proveedores.importar');
        Route::resource('proveedores', ProveedorController::class)->only(['index', 'store', 'update'])->parameters(['proveedores' => 'proveedor']);
    });
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy')->middleware('permiso:admin')->where(['proveedor' => '[0-9]+']);

    // Compras (eliminar: solo admin)
    Route::middleware('permiso:compras')->group(function () {
        Route::post('/compras/importar-csv', [CompraController::class, 'importarCsv'])->name('compras.importar-csv');
        Route::post('/compras/importar-siat', [CompraController::class, 'importarSiat'])->name('compras.importar-siat');
        Route::post('/compras/importar-excel', [CompraController::class, 'importarExcel'])->name('compras.importar-excel');
        Route::resource('compras', CompraController::class)->except(['show', 'destroy']);
    });
    Route::delete('/compras/{compra}', [CompraController::class, 'destroy'])->name('compras.destroy')->middleware('permiso:admin');

    // Ventas (operar: admin/vendedor; anular/eliminar: solo admin)
    Route::middleware('permiso:ventas')->group(function () {
        Route::post('/ventas/importar-csv', [VentaController::class, 'importarCsv'])->name('ventas.importar-csv');
        Route::post('/ventas/importar-siat', [VentaController::class, 'importarSiat'])->name('ventas.importar-siat');
        Route::post('/ventas/importar-excel', [VentaController::class, 'importarExcel'])->name('ventas.importar-excel');
        Route::resource('ventas', VentaController::class)->except(['show', 'destroy']);
    });
    Route::post('/ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular')->middleware('permiso:admin');
    Route::delete('/ventas/{venta}', [VentaController::class, 'destroy'])->name('ventas.destroy')->middleware('permiso:admin');

    // Comprobantes (eliminar: solo admin)
    Route::middleware('permiso:comprobantes')->group(function () {
        Route::get('/comprobantes/{comprobante}/imprimir', [ComprobanteController::class, 'imprimir'])->name('comprobantes.imprimir');
        Route::post('/comprobantes/{comprobante}/pagos', [ComprobanteController::class, 'agregarPago'])->name('comprobantes.pagos.store');
        Route::delete('/comprobantes/{comprobante}/pagos/{pago}', [ComprobanteController::class, 'quitarPago'])->name('comprobantes.pagos.destroy');
        Route::resource('comprobantes', ComprobanteController::class)->except(['create', 'destroy']);
    });
    Route::delete('/comprobantes/{comprobante}', [ComprobanteController::class, 'destroy'])->name('comprobantes.destroy')->middleware('permiso:admin');

    // Proformas (eliminar: solo admin)
    Route::middleware('permiso:proformas')->group(function () {
        Route::get('/proformas/buscar-producto', [ProformaController::class, 'buscarProducto'])->name('proformas.buscar-producto');
        Route::post('/proformas/{proforma}/cambiar-estado', [ProformaController::class, 'cambiarEstado'])->name('proformas.cambiar-estado');
        Route::post('/proformas/{proforma}/convertir-venta', [ProformaController::class, 'convertirAVenta'])->name('proformas.convertir-venta');
        Route::resource('proformas', ProformaController::class)->except(['destroy']);
    });
    Route::delete('/proformas/{proforma}', [ProformaController::class, 'destroy'])->name('proformas.destroy')->middleware('permiso:admin');

    // CRM comercial
    Route::middleware('permiso:crm')->prefix('crm')->name('crm.')->group(function () {
        Route::get('/', [CrmController::class, 'index'])->name('index');
        Route::post('/leads', [CrmController::class, 'storeLead'])->name('leads.store');
        Route::get('/leads/{lead}', [CrmController::class, 'showLead'])->name('leads.show');
        Route::put('/leads/{lead}', [CrmController::class, 'updateLead'])->name('leads.update');
        Route::patch('/leads/{lead}/etapa', [CrmController::class, 'cambiarEtapa'])->name('leads.etapa');
        Route::patch('/leads/{lead}/ficha', [CrmController::class, 'actualizarFicha'])->name('leads.ficha');
        Route::post('/leads/{lead}/mensajes', [CrmController::class, 'enviarMensaje'])->name('leads.mensajes.store');
        Route::post('/leads/{lead}/mensajes/{mensaje}/reintentar', [CrmController::class, 'reintentarMensaje'])->name('leads.mensajes.reintentar');
    });
    Route::middleware('permiso:crm.administrar')->prefix('crm')->name('crm.')->group(function () {
        Route::get('/diagnostico', [CrmController::class, 'diagnostico'])->name('diagnostico');
        Route::get('/canales', [CrmController::class, 'canales'])->name('canales');
        Route::post('/canales', [CrmController::class, 'storeCanal'])->name('canales.store');
        Route::put('/canales/{canalWhatsapp}', [CrmController::class, 'updateCanal'])->name('canales.update');
        Route::post('/canales/{canalWhatsapp}/probar-meta', [CrmController::class, 'probarCanalMeta'])->name('canales.probar-meta');
    });

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index')->middleware('permiso:reportes');

    // Tributario
    Route::get('/tributario', [TributarioController::class, 'index'])->name('tributario.index')->middleware('permiso:tributario');

    // Configuración (solo admin)
    Route::middleware('permiso:configuracion')->group(function () {
        Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
        Route::post('/configuracion/siat', [ConfiguracionController::class, 'updateSiat'])->name('configuracion.siat');
        Route::post('/configuracion/probar-siat', [ConfiguracionController::class, 'probarSiat'])->name('configuracion.probar-siat');
        Route::post('/configuracion/sincronizar', [ConfiguracionController::class, 'sincronizarSiat'])->name('configuracion.sincronizar');
        Route::post('/configuracion/resetear', [ConfiguracionController::class, 'resetearDatos'])->name('configuracion.resetear');
    });

    // Facturación electrónica SIAT
    Route::middleware('permiso:facturas.ver')->group(function () {
        Route::get('/facturas/reporte-anulaciones', [FacturaController::class, 'reporteAnulaciones'])->name('facturas.reporte');
        Route::get('/facturas/{factura}/pdf', [FacturaController::class, 'descargarPdf'])->name('facturas.pdf');
        Route::get('/facturas/{factura}/pdf-medio-oficio', [FacturaController::class, 'descargarPdfMedioOficio'])->name('facturas.pdf-medio-oficio');
        Route::get('/facturas/{factura}/pdf-rollo', [FacturaController::class, 'descargarPdfRollo'])->name('facturas.pdf-rollo');
        Route::get('/facturas/{factura}/pdf-rollo-58', [FacturaController::class, 'descargarPdfRollo58'])->name('facturas.pdf-rollo-58');
        Route::get('/facturas/{factura}/xml', [FacturaController::class, 'descargarXml'])->name('facturas.xml');
        Route::post('/facturas/{factura}/enviar-correo', [FacturaController::class, 'reenviarCorreo'])->name('facturas.enviar-correo');
        Route::get('/contingencias', [EventoContingenciaController::class, 'index'])->name('contingencias.index');
        Route::resource('facturas', FacturaController::class)->only(['index', 'show']);
    });
    Route::middleware('permiso:facturas.emitir')->group(function () {
        Route::post('/facturas/emitir/{venta}', [FacturaController::class, 'emitir'])->name('facturas.emitir');
        Route::post('/facturas/{factura}/anular', [FacturaController::class, 'anular'])->name('facturas.anular');
        Route::post('/facturas/{factura}/revertir', [FacturaController::class, 'revertir'])->name('facturas.revertir');
        Route::post('/facturas/{factura}/notas', [FacturaController::class, 'emitirNota'])->name('facturas.notas.store');
        Route::post('/contingencias', [EventoContingenciaController::class, 'abrir'])->name('contingencias.abrir');
        Route::post('/contingencias/{evento}/cerrar', [EventoContingenciaController::class, 'cerrar'])->name('contingencias.cerrar');
        Route::post('/contingencias/{evento}/empaquetar', [EventoContingenciaController::class, 'empaquetar'])->name('contingencias.empaquetar');
        Route::post('/contingencias/{evento}/validar', [EventoContingenciaController::class, 'validar'])->name('contingencias.validar');
        Route::get('/contingencias/{evento}/descargar', [EventoContingenciaController::class, 'descargar'])->name('contingencias.descargar');
    });
    Route::delete('/facturas/{factura}', [FacturaController::class, 'destroy'])->name('facturas.destroy')->middleware('permiso:admin');

    // Catálogos SIN
    Route::get('/catalogos', [CatalogoController::class, 'index'])->name('catalogos.index')->middleware('permiso:configuracion');
    Route::post('/catalogos/sincronizar', [CatalogoController::class, 'sincronizar'])->name('catalogos.sincronizar')->middleware('permiso:configuracion');
    Route::get('/catalogos/productos', [CatalogoController::class, 'buscarProducto'])->name('catalogos.productos')->middleware('permiso:productos');
    Route::get('/catalogos/unidades', [CatalogoController::class, 'buscarUnidad'])->name('catalogos.unidades')->middleware('permiso:productos');

    // Sucursales y Puntos de Venta (Multisucursal SIAT)
    Route::middleware('permiso:configuracion')->group(function () {
        Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
        Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
        Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->name('sucursales.update');
        Route::delete('/sucursales/{sucursal}', [SucursalController::class, 'destroy'])->name('sucursales.destroy');
        Route::post('/sucursales/{sucursal}/puntos-venta', [SucursalController::class, 'storePuntoVenta'])->name('sucursales.puntos-venta.store');
        Route::put('/puntos-venta/{puntoVenta}', [SucursalController::class, 'updatePuntoVenta'])->name('sucursales.puntos-venta.update');
        Route::post('/puntos-venta/{puntoVenta}/cuis', [SucursalController::class, 'solicitarCuis'])->name('sucursales.puntos-venta.cuis');
        Route::post('/puntos-venta/{puntoVenta}/cufd', [SucursalController::class, 'solicitarCufd'])->name('sucursales.puntos-venta.cufd');
        Route::post('/sucursales/cambiar-activa', [SucursalController::class, 'cambiarActiva'])->name('sucursales.cambiar-activa');
    });

    // Cuentas por cobrar/pagar
    Route::get('/cuentas', [CajaController::class, 'cuentas'])->name('cuentas.index')->middleware('permiso:comprobantes');
    Route::post('/cuentas/cobrar/{venta}', [CajaController::class, 'cobrar'])->name('cuentas.cobrar')->middleware('permiso:comprobantes');
    Route::post('/cuentas/pagar/{compra}', [CajaController::class, 'pagar'])->name('cuentas.pagar')->middleware('permiso:comprobantes');

    // Caja diaria
    Route::get('/caja', [CajaController::class, 'index'])->name('caja.index')->middleware('permiso:comprobantes');

    // Finanzas
    Route::get('/finanzas', [FinanzaController::class, 'index'])
        ->name('finanzas.index')
        ->middleware('permiso:finanzas');
    Route::post(
        '/finanzas',
        [FinanzaController::class, 'store']
    )
        ->name('finanzas.store')
        ->middleware('permiso:finanzas');

    // Kardex e inventario valorizado
    Route::get('/kardex', [KardexController::class, 'index'])->name('kardex.index')->middleware('permiso:productos');
    Route::get('/kardex/{producto}', [KardexController::class, 'show'])->name('kardex.show')->middleware('permiso:productos');

    // Administración (solo admin)
    Route::middleware('permiso:admin')->group(function () {
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/papelera', [PapeleraController::class, 'index'])->name('papelera.index');
        Route::post('/papelera/{modelo}/{id}/restaurar', [PapeleraController::class, 'restaurar'])->name('papelera.restaurar');
        Route::delete('/papelera/{modelo}/{id}', [PapeleraController::class, 'eliminar'])->name('papelera.eliminar');
        Route::get('/respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
        Route::post('/respaldos', [RespaldoController::class, 'crear'])->name('respaldos.crear');
        Route::get('/respaldos/{archivo}', [RespaldoController::class, 'descargar'])->name('respaldos.descargar')->where('archivo', '.*');
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
        Route::post('/roles', [UsuarioController::class, 'storeRol'])->name('roles.store');
        Route::delete('/roles/{rol}', [UsuarioController::class, 'destroyRol'])->name('roles.destroy');
        Route::post('/permisos/toggle', [UsuarioController::class, 'toggle'])->name('permisos.toggle');
    });
});
