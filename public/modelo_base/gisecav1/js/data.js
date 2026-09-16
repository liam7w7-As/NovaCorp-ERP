/* ============================================================
   GISECA SRL - Capa de datos (localStorage como "base de datos")
   Todo el sistema lee y escribe aquí. No requiere servidor.
   ============================================================ */

const DB = {
  KEYS: {
    productos: 'giseca_productos',
    clientes: 'giseca_clientes',
    proveedores: 'giseca_proveedores',
    proformas: 'giseca_proformas',
    compras: 'giseca_compras',
    ventas: 'giseca_ventas',
    comprobantes: 'giseca_comprobantes',
    contador: 'giseca_contador_proforma',
    contadores_doc: 'giseca_contadores_doc',
    sesion: 'giseca_sesion',
    membretado: 'giseca_membretado',
  },

  _get(clave, porDefecto) {
    const raw = localStorage.getItem(clave);
    return raw ? JSON.parse(raw) : porDefecto;
  },
  _set(clave, valor) {
    localStorage.setItem(clave, JSON.stringify(valor));
  },

  // ---- Productos ----
  productos() { return this._get(this.KEYS.productos, []); },
  guardarProductos(lista) { this._set(this.KEYS.productos, lista); },
  siguienteIdProducto() {
    const l = this.productos();
    return l.length ? Math.max(...l.map(p => p.id)) + 1 : 1;
  },
  crearProducto(p) {
    const l = this.productos();
    p.id = this.siguienteIdProducto();
    p.activo = true;
    l.push(p);
    this.guardarProductos(l);
    return p;
  },
  actualizarProducto(id, cambios) {
    const l = this.productos();
    const i = l.findIndex(p => p.id === id);
    if (i >= 0) { l[i] = { ...l[i], ...cambios }; this.guardarProductos(l); }
  },
  ajustarStock(id, delta) {
    const l = this.productos();
    const i = l.findIndex(p => p.id === id);
    if (i >= 0) { l[i].stock = Math.round((l[i].stock + delta) * 100) / 100; this.guardarProductos(l); }
  },
  buscarProductos(termino) {
    const t = termino.toLowerCase();
    return this.productos().filter(p => p.activo !== false && (
      p.codigo.toLowerCase().includes(t) ||
      (p.equivalente || '').toLowerCase().includes(t) ||
      p.descripcion.toLowerCase().includes(t)
    ));
  },

  // ---- Clientes ----
  clientes() { return this._get(this.KEYS.clientes, []); },
  guardarClientes(lista) { this._set(this.KEYS.clientes, lista); },
  siguienteIdCliente() {
    const l = this.clientes();
    return l.length ? Math.max(...l.map(c => c.id)) + 1 : 1;
  },
  crearCliente(c) {
    const l = this.clientes();
    c.id = this.siguienteIdCliente();
    l.push(c);
    this.guardarClientes(l);
    return c;
  },

  // ---- Proformas ----
  proformas() { return this._get(this.KEYS.proformas, []); },
  guardarProformas(lista) { this._set(this.KEYS.proformas, lista); },
  siguienteNumeroProforma() {
    let n = this._get(this.KEYS.contador, 0) + 1;
    this._set(this.KEYS.contador, n);
    return 'PR-' + String(n).padStart(6, '0');
  },
  crearProforma(pf) {
    const l = this.proformas();
    pf.id = l.length ? Math.max(...l.map(p => p.id)) + 1 : 1;
    pf.numero = this.siguienteNumeroProforma();
    l.push(pf);
    this.guardarProformas(l);

    // Reserva de stock: solo anota la intención, no descuenta stock real todavía
    return pf;
  },
  buscarProformaPorId(id) {
    return this.proformas().find(p => p.id === Number(id));
  },
  cambiarEstadoProforma(id, estado) {
    const l = this.proformas();
    const i = l.findIndex(p => p.id === Number(id));
    if (i >= 0) { l[i].estado = estado; this.guardarProformas(l); }
  },
  convertirProformaAVenta(id, tipo, modalidad) {
    const pf = this.buscarProformaPorId(id);
    if (!pf || pf.estado === 'CONVERTIDA') return false;

    const items = pf.items.map(it => ({
      id_producto: it.id_producto, codigo: it.codigo, descripcion: it.descripcion,
      cantidad: it.cantidad, precio: it.precio,
    }));

    const venta = this.crearVenta({
      tipo: tipo || 'SIN_FACTURA', modalidad: modalidad || 'CONTADO',
      fecha: new Date().toISOString().slice(0, 10),
      clienteNombre: pf.clienteNombre, descuento: pf.descuento,
      idProformaOrigen: pf.id, metodo: 'Efectivo',
    }, items);

    this.cambiarEstadoProforma(id, 'CONVERTIDA');
    return venta;
  },

  // ============================================================
  // IMPORTACIÓN MASIVA DE INVENTARIO DESDE EXCEL
  // ============================================================
  // filas: array de objetos ya parseados por SheetJS (una fila = un producto).
  // Columnas esperadas: Codigo, Equivalente, Marca, Descripcion, Unidad, Costo, PrecioVenta, Stock, StockMinimo
  // Si el código ya existe, ACTUALIZA el producto (precio/costo/stock mínimo/descripción);
  // si no existe, lo CREA. El stock existente no se sobreescribe a la baja por accidente:
  // se SUMA el stock indicado en el Excel al stock actual (pensado para reposición masiva).
  importarProductosExcel(filas) {
    let creados = 0, actualizados = 0;
    const errores = [];

    filas.forEach((fila, i) => {
      try {
        // Acepta encabezados con o sin tildes/espacios distintos
        const obtener = (...claves) => {
          for (const clave of claves) {
            const llave = Object.keys(fila).find(k => k.trim().toLowerCase() === clave.toLowerCase());
            if (llave !== undefined && fila[llave] !== undefined && fila[llave] !== '') return fila[llave];
          }
          return '';
        };

        const codigo = String(obtener('Codigo', 'Código') || '').trim();
        if (!codigo) return; // fila vacía o sin código, se ignora silenciosamente

        const descripcion = String(obtener('Descripcion', 'Descripción') || codigo).trim();
        const equivalente = String(obtener('Equivalente') || '').trim();
        const marca = String(obtener('Marca') || '').trim();
        const unidad = String(obtener('Unidad') || 'PZA').trim() || 'PZA';
        const costo = parseFloat(obtener('Costo')) || 0;
        const precio = parseFloat(obtener('PrecioVenta', 'Precio Venta', 'Precio')) || 0;
        const stockExcel = parseFloat(obtener('Stock')) || 0;
        const stockMin = parseFloat(obtener('StockMinimo', 'Stock Minimo', 'Stock Mínimo')) || 0;

        const existente = this.productos().find(p => p.codigo.toLowerCase() === codigo.toLowerCase());

        if (existente) {
          this.actualizarProducto(existente.id, {
            descripcion, equivalente, marca, unidad, costo,
            precio: precio || existente.precio,
            stockMin: stockMin || existente.stockMin,
          });
          if (stockExcel > 0) this.ajustarStock(existente.id, stockExcel); // suma reposición
          actualizados++;
        } else {
          this.crearProducto({ codigo, descripcion, equivalente, marca, unidad, costo, precio, stock: stockExcel, stockMin });
          creados++;
        }
      } catch (e) {
        errores.push('Fila ' + (i + 2) + ': ' + e.message);
      }
    });

    return { creados, actualizados, errores };
  },

  // ============================================================
  // IMPORTACIÓN DESDE EL SIAT (Libro de Compras / Libro de Ventas IVA)
  // ============================================================
  // Estos archivos NO traen detalle de productos (son el resumen fiscal
  // por factura que exige el SIN), así que NO tocan el stock del inventario.
  // Sí reconocen: NIT, razón social, N° factura, fecha, monto total,
  // base para crédito/débito fiscal, y el crédito/débito fiscal mismo.
  // Cada factura importada genera su comprobante de Egreso (compras) o
  // Ingreso (ventas) automáticamente, y evita duplicados si se reimporta
  // el mismo archivo (se identifica por N° de autorización del SIN).
  _normalizarEncabezado(s) {
    return String(s || '').trim().toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  },

  _obtenerCampo(fila, ...posiblesNombres) {
    for (const nombre of posiblesNombres) {
      const objetivo = this._normalizarEncabezado(nombre);
      const llave = Object.keys(fila).find(k => this._normalizarEncabezado(k) === objetivo);
      if (llave !== undefined && fila[llave] !== undefined) return String(fila[llave]).trim();
    }
    return '';
  },

  _fechaSIATaISO(fechaDDMMYYYY) {
    const partes = (fechaDDMMYYYY || '').split('/');
    if (partes.length !== 3) return new Date().toISOString().slice(0,10);
    const [d, m, y] = partes;
    return `${y}-${m.padStart(2,'0')}-${d.padStart(2,'0')}`;
  },

  importarComprasSIAT(filas) {
    let creadas = 0, duplicadas = 0, errores = [];
    const yaImportados = new Set(this.compras().filter(c => c.codigoAutorizacion).map(c => c.codigoAutorizacion));

    filas.forEach((fila, i) => {
      try {
        const codigoAutorizacion = this._obtenerCampo(fila, 'CODIGO DE AUTORIZACION');
        if (!codigoAutorizacion) return; // fila vacía o de otro formato

        if (yaImportados.has(codigoAutorizacion)) { duplicadas++; return; }

        const nit = this._obtenerCampo(fila, 'NIT PROVEEDOR');
        const razonSocial = this._obtenerCampo(fila, 'RAZON SOCIAL PROVEEDOR') || 'Proveedor SIAT s/n';
        const numeroFacturaSIAT = this._obtenerCampo(fila, 'NUMERO FACTURA');
        const fecha = this._fechaSIATaISO(this._obtenerCampo(fila, 'FECHA DE FACTURA/DUI/DIM'));
        const importeTotal = parseFloat(this._obtenerCampo(fila, 'IMPORTE TOTAL COMPRA')) || 0;
        const baseCF = parseFloat(this._obtenerCampo(fila, 'IMPORTE BASE CF')) || 0;
        const creditoFiscal = parseFloat(this._obtenerCampo(fila, 'CREDITO FISCAL')) || 0;
        const tipoCompra = this._obtenerCampo(fila, 'TIPO COMPRA');
        const conDerechoCF = this._obtenerCampo(fila, 'CON DERECHO A CREDITO FISCAL').toUpperCase() === 'SI';

        let proveedor = this.proveedores().find(p => p.nit === nit);
        if (!proveedor) proveedor = this.crearProveedor({ nombre: razonSocial, nit, telefono: '' });

        const numero = this.siguienteNumeroDocumento('FC-');
        const l = this.compras();
        const compra = {
          id: l.length ? Math.max(...l.map(c => c.id)) + 1 : 1,
          numero, tipo: 'CON_FACTURA', fecha, proveedorNombre: proveedor.nombre,
          items: [], subtotal: importeTotal, descuento: 0, total: importeTotal,
          origen: 'SIAT', numeroFacturaSIAT, codigoAutorizacion, nitProveedor: nit,
          baseCF, creditoFiscal, tipoCompra, conDerechoCF,
        };
        l.push(compra);
        this.guardarCompras(l);

        const comp = this.crearComprobante({
          tipo: 'EGRESO',
          concepto: `Compra según Libro de Compras SIAT — Factura N° ${numeroFacturaSIAT}`,
          entidad: proveedor.nombre,
          monto: importeTotal, referencia: numero, metodo: 'Registro SIAT',
          fecha,
        });
        compra.comprobante = comp.numero;
        this.guardarCompras(this.compras().map(c => c.id === compra.id ? compra : c));

        yaImportados.add(codigoAutorizacion);
        creadas++;
      } catch (e) {
        errores.push('Fila ' + (i + 2) + ': ' + e.message);
      }
    });

    return { creadas, duplicadas, errores };
  },

  importarVentasSIAT(filas) {
    let creadas = 0, duplicadas = 0, errores = [];
    const yaImportados = new Set(this.ventas().filter(v => v.codigoAutorizacion).map(v => v.codigoAutorizacion));

    filas.forEach((fila, i) => {
      try {
        const codigoAutorizacion = this._obtenerCampo(fila, 'CODIGO DE AUTORIZACION');
        if (!codigoAutorizacion) return;

        if (yaImportados.has(codigoAutorizacion)) { duplicadas++; return; }

        const nit = this._obtenerCampo(fila, 'NIT / CI CLIENTE', 'NIT/CI CLIENTE', 'NIT CLIENTE');
        const razonSocial = this._obtenerCampo(fila, 'RAZON SOCIAL / NOMBRE', 'RAZON SOCIAL', 'NOMBRE O RAZON SOCIAL') || 'Cliente SIAT s/n';
        const numeroFacturaSIAT = this._obtenerCampo(fila, 'Nº DE LA FACTURA', 'NUMERO FACTURA', 'N° DE LA FACTURA', 'NRO. DE LA FACTURA', 'NRO DE LA FACTURA');
        const fecha = this._fechaSIATaISO(this._obtenerCampo(fila, 'FECHA DE LA FACTURA', 'FECHA DE FACTURA'));
        const importeTotal = parseFloat(this._obtenerCampo(fila, 'IMPORTE TOTAL DE LA VENTA', 'IMPORTE TOTAL VENTA')) || 0;
        const baseDF = parseFloat(this._obtenerCampo(fila, 'IMPORTE BASE PARA DEBITO FISCAL', 'IMPORTE BASE DF')) || 0;
        const debitoFiscal = parseFloat(this._obtenerCampo(fila, 'DEBITO FISCAL')) || 0;
        // El SIAT marca cada factura como VALIDA o ANULADA. Una factura anulada no debe
        // contarse como ingreso real (el dinero nunca entró), pero sí se guarda para
        // trazabilidad, marcada como ANULADA, sin generar su comprobante de ingreso.
        const estadoSIAT = this._obtenerCampo(fila, 'ESTADO').toUpperCase();
        const esAnulada = estadoSIAT === 'ANULADA';

        let cliente = this.clientes().find(c => c.nit === nit);
        if (!cliente) cliente = this.crearCliente({ nombre: razonSocial, nit, telefono: '', correo: '', contacto: '' });

        const numero = this.siguienteNumeroDocumento('FV-');
        const l = this.ventas();
        const venta = {
          id: l.length ? Math.max(...l.map(v => v.id)) + 1 : 1,
          numero, tipo: 'CON_FACTURA', modalidad: 'CONTADO', fecha, clienteNombre: cliente.nombre,
          items: [], subtotal: importeTotal, descuento: 0, total: importeTotal,
          estado: esAnulada ? 'ANULADA' : 'ACTIVA',
          origen: 'SIAT', numeroFacturaSIAT, codigoAutorizacion, nitCliente: nit,
          baseDF: esAnulada ? 0 : baseDF, debitoFiscal: esAnulada ? 0 : debitoFiscal,
        };
        l.push(venta);
        this.guardarVentas(l);

        // Si la factura está ANULADA en el SIAT, no genera comprobante de ingreso
        // (ese dinero nunca entró) — solo queda registrada para trazabilidad.
        if (esAnulada) {
          yaImportados.add(codigoAutorizacion);
          creadas++;
          return;
        }

        const comp = this.crearComprobante({
          tipo: 'INGRESO',
          concepto: `Venta SIAT Fact. ${numeroFacturaSIAT} — ${cliente.nombre}`,
          entidad: cliente.nombre,
          monto: importeTotal, referencia: numero, metodo: 'Registro SIAT',
          fecha,
        });
        venta.comprobante = comp.numero;
        this.guardarVentas(this.ventas().map(v => v.id === venta.id ? venta : v));

        yaImportados.add(codigoAutorizacion);
        creadas++;
      } catch (e) {
        errores.push('Fila ' + (i + 2) + ': ' + e.message);
      }
    });

    return { creadas, duplicadas, errores };
  },

  // Totales fiscales reales, calculados de todo lo importado del SIAT (para el módulo Tributario)
  resumenTributarioSIAT() {
    const creditoFiscal = this.compras().filter(c => c.origen === 'SIAT').reduce((s,c) => s + (c.creditoFiscal || 0), 0);
    const debitoFiscal = this.ventas().filter(v => v.origen === 'SIAT' && v.estado === 'ACTIVA').reduce((s,v) => s + (v.debitoFiscal || 0), 0);
    return { creditoFiscal, debitoFiscal, saldo: debitoFiscal - creditoFiscal };
  },

  // ---- Sesión (solo para bloquear pantallas en este equipo, no es seguridad real) ----
  haySesion() { return localStorage.getItem(this.KEYS.sesion) === '1'; },
  iniciarSesion() { localStorage.setItem(this.KEYS.sesion, '1'); },
  cerrarSesion() { localStorage.removeItem(this.KEYS.sesion); },

  // ---- Membretado ----
  membretado() { return this._get(this.KEYS.membretado, null); },
  guardarMembretado(m) { this._set(this.KEYS.membretado, m); },
  quitarMembretado() { localStorage.removeItem(this.KEYS.membretado); },

  // ============================================================
  // PROVEEDORES
  // ============================================================
  proveedores() { return this._get(this.KEYS.proveedores, []); },
  guardarProveedores(lista) { this._set(this.KEYS.proveedores, lista); },
  crearProveedor(p) {
    const l = this.proveedores();
    p.id = l.length ? Math.max(...l.map(x => x.id)) + 1 : 1;
    l.push(p);
    this.guardarProveedores(l);
    return p;
  },
  obtenerOCrearProveedorPorNombre(nombre) {
    nombre = (nombre || 'Proveedor sin nombre').trim();
    const existente = this.proveedores().find(p => p.nombre.toLowerCase() === nombre.toLowerCase());
    if (existente) return existente;
    return this.crearProveedor({ nombre, nit: '', telefono: '' });
  },

  // ============================================================
  // NUMERACIÓN AUTOMÁTICA POR PREFIJO (FC-, SF-, FV-, NV-, ING-, EGR-)
  // ============================================================
  siguienteNumeroDocumento(prefijo) {
    const contadores = this._get(this.KEYS.contadores_doc, {});
    const n = (contadores[prefijo] || 0) + 1;
    contadores[prefijo] = n;
    this._set(this.KEYS.contadores_doc, contadores);
    return prefijo + String(n).padStart(6, '0');
  },

  // ============================================================
  // COMPROBANTES DE INGRESO / EGRESO (se generan automáticamente)
  // ============================================================
  comprobantes() { return this._get(this.KEYS.comprobantes, []); },
  guardarComprobantes(lista) { this._set(this.KEYS.comprobantes, lista); },
  crearComprobante({ tipo, concepto, monto, referencia, metodo, fecha, entidad, banco, cuenta, numeroReferencia, nota }) {
    const l = this.comprobantes();
    const numero = this.siguienteNumeroDocumento(tipo === 'INGRESO' ? 'ING-' : 'EGR-');
    const metodoFinal = metodo || 'Efectivo';
    const ahora = new Date();
    const comp = {
      id: l.length ? Math.max(...l.map(c => c.id)) + 1 : 1,
      tipo, numero, concepto, monto,
      referencia: referencia || '',
      entidad: entidad || '', // Beneficiario (egreso) o quien paga/Recibido de (ingreso)
      nota: nota || '', // observación libre, ej. "pagó mitad en efectivo y mitad transferencia"
      metodo: metodoFinal,
      fecha: fecha || ahora.toISOString().slice(0, 10),
      hora: ahora.toTimeString().slice(0, 5),
      // desglose de forma de pago, editable después. Cada forma puede traer datos bancarios propios y su propia nota.
      pagos: [{ metodo: metodoFinal, monto, banco: banco || '', cuenta: cuenta || '', numeroReferencia: numeroReferencia || '', nota: '' }],
    };
    l.push(comp);
    this.guardarComprobantes(l);
    return comp;
  },
  buscarComprobantePorId(id) { return this.comprobantes().find(c => c.id === Number(id)); },

  // Crear un comprobante manual (no ligado a ninguna compra/venta), ej. para registrar
  // otros movimientos de caja: aportes, gastos varios, retiros, etc.
  crearComprobanteManual(datos) {
    return this.crearComprobante(datos);
  },

  // Edita los datos generales del comprobante (concepto, monto, fecha, referencia, entidad).
  // No toca el desglose de pagos — para eso usar agregarPagoComprobante/quitarPagoComprobante.
  actualizarComprobante(id, cambios) {
    const l = this.comprobantes();
    const i = l.findIndex(c => c.id === Number(id));
    if (i < 0) return null;
    l[i] = { ...l[i], ...cambios };
    this.guardarComprobantes(l);
    return l[i];
  },

  // Agrega una forma de pago al desglose (ej. "parte transferencia, parte efectivo"),
  // con sus datos bancarios propios si corresponde (banco, cuenta, N° de referencia).
  agregarPagoComprobante(id, metodo, monto, banco, cuenta, numeroReferencia, nota) {
    const l = this.comprobantes();
    const i = l.findIndex(c => c.id === Number(id));
    if (i < 0) return null;
    if (!l[i].pagos) l[i].pagos = l[i].metodo ? [{ metodo: l[i].metodo, monto: l[i].monto }] : [];
    l[i].pagos.push({ metodo, monto: Number(monto), banco: banco || '', cuenta: cuenta || '', numeroReferencia: numeroReferencia || '', nota: nota || '' });
    // Si hay más de una forma de pago, el método principal se muestra como "Múltiple"
    l[i].metodo = l[i].pagos.length > 1 ? 'Múltiple' : l[i].pagos[0].metodo;
    this.guardarComprobantes(l);
    return l[i];
  },

  // Quita una forma de pago del desglose por su posición (índice).
  quitarPagoComprobante(id, indice) {
    const l = this.comprobantes();
    const i = l.findIndex(c => c.id === Number(id));
    if (i < 0) return null;
    l[i].pagos.splice(indice, 1);
    l[i].metodo = l[i].pagos.length > 1 ? 'Múltiple' : (l[i].pagos[0] ? l[i].pagos[0].metodo : 'Efectivo');
    this.guardarComprobantes(l);
    return l[i];
  },

  // Cuánto del monto total ya quedó asignado a alguna forma de pago (para mostrar el saldo restante)
  totalAsignadoComprobante(comp) {
    return (comp.pagos || []).reduce((s, p) => s + Number(p.monto || 0), 0);
  },

  eliminarComprobante(id) {
    this.guardarComprobantes(this.comprobantes().filter(c => c.id !== Number(id)));
  },

  // ============================================================
  // COMPRAS (con factura / sin factura)
  // ============================================================
  compras() { return this._get(this.KEYS.compras, []); },
  guardarCompras(lista) { this._set(this.KEYS.compras, lista); },
  buscarCompraPorId(id) { return this.compras().find(c => c.id === Number(id)); },

  // Arma una descripción legible de "qué se compró/vendió" a partir del detalle de productos,
  // para usar como Concepto del comprobante (en vez de repetir el nombre del proveedor/cliente).
  _describirItems(items) {
    if (!items || !items.length) return '';
    const partes = items.map(it => `${it.descripcion || it.codigo} (x${it.cantidad})`);
    if (partes.length <= 3) return partes.join(', ');
    return partes.slice(0, 3).join(', ') + ` y ${partes.length - 3} producto(s) más`;
  },

  /**
   * cabecera: { tipo:'CON_FACTURA'|'SIN_FACTURA', fecha, proveedorNombre, descuento, metodo }
   * items: [{ id_producto, codigo, descripcion, cantidad, costo }]
   * Efectos reales: sube stock, registra costo, crea comprobante de EGRESO.
   */
  crearCompra(cabecera, items) {
    const subtotal = items.reduce((s, it) => s + it.cantidad * it.costo, 0);
    const descuento = Number(cabecera.descuento || 0);
    const total = subtotal - descuento;
    const prefijo = cabecera.tipo === 'CON_FACTURA' ? 'FC-' : 'SF-';
    const numero = cabecera.numero || this.siguienteNumeroDocumento(prefijo);

    const l = this.compras();
    const compra = {
      id: l.length ? Math.max(...l.map(c => c.id)) + 1 : 1,
      numero, tipo: cabecera.tipo, fecha: cabecera.fecha,
      proveedorNombre: cabecera.proveedorNombre,
      items, subtotal, descuento, total,
    };
    l.push(compra);
    this.guardarCompras(l);

    // Sube stock real y actualiza costo de cada producto
    items.forEach(it => {
      if (it.id_producto) {
        this.ajustarStock(it.id_producto, it.cantidad);
        this.actualizarProducto(it.id_producto, { costo: it.costo });
      }
    });

    // Comprobante de EGRESO automático (dinero que sale).
    // Beneficiario = a quién le compramos. Descripción = qué se compró (no repetir el proveedor).
    const comp = this.crearComprobante({
      tipo: 'EGRESO',
      concepto: this._describirItems(items) || `Compra ${numero}`,
      entidad: cabecera.proveedorNombre,
      monto: total,
      referencia: numero,
      metodo: cabecera.metodo,
      fecha: cabecera.fecha,
    });
    compra.comprobante = comp.numero;
    this.guardarCompras(l.map(c => c.id === compra.id ? compra : c));

    return compra;
  },

  /**
   * Edita una compra existente. Si trae items (compra manual), revierte el stock
   * que había sumado la versión anterior y aplica el de la nueva. Si no trae items
   * (compra importada del SIAT, sin detalle de productos), solo actualiza los datos
   * de cabecera (proveedor, fecha, montos, crédito fiscal) sin tocar stock.
   */
  actualizarCompra(id, cabecera, items) {
    const l = this.compras();
    const i = l.findIndex(c => c.id === Number(id));
    if (i < 0) return null;
    const original = l[i];

    if (items) {
      // Revertir stock de la versión anterior
      original.items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, -it.cantidad); });

      const subtotal = items.reduce((s, it) => s + it.cantidad * it.costo, 0);
      const descuento = Number(cabecera.descuento || 0);
      const total = subtotal - descuento;

      l[i] = { ...original, tipo: cabecera.tipo, fecha: cabecera.fecha, proveedorNombre: cabecera.proveedorNombre, items, subtotal, descuento, total };

      // Aplicar stock de la versión nueva
      items.forEach(it => {
        if (it.id_producto) {
          this.ajustarStock(it.id_producto, it.cantidad);
          this.actualizarProducto(it.id_producto, { costo: it.costo });
        }
      });
    } else {
      // Compra sin detalle de productos (ej. importada del SIAT): solo cabecera/montos
      l[i] = { ...original, ...cabecera, total: cabecera.total !== undefined ? cabecera.total : original.total };
    }

    this.guardarCompras(l);

    // Mantener sincronizado el comprobante de egreso asociado:
    // Beneficiario = proveedor actualizado. Descripción = qué se compró (o el origen SIAT si no hay detalle).
    if (l[i].comprobante) {
      const comp = this.comprobantes().find(c => c.numero === l[i].comprobante);
      if (comp) {
        const nuevoConcepto = items
          ? (this._describirItems(items) || `Compra ${l[i].numero}`)
          : `Compra según Libro de Compras SIAT — Factura N° ${l[i].numeroFacturaSIAT || ''}`;
        this.actualizarComprobante(comp.id, { monto: l[i].total, concepto: nuevoConcepto, entidad: l[i].proveedorNombre, fecha: l[i].fecha });
      }
    }
    return l[i];
  },

  // Elimina una compra: revierte el stock que había sumado y borra su comprobante de egreso.
  eliminarCompra(id) {
    const compra = this.buscarCompraPorId(id);
    if (!compra) return false;
    compra.items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, -it.cantidad); });
    if (compra.comprobante) {
      const comp = this.comprobantes().find(c => c.numero === compra.comprobante);
      if (comp) this.eliminarComprobante(comp.id);
    }
    this.guardarCompras(this.compras().filter(c => c.id !== Number(id)));
    return true;
  },

  // ============================================================
  // VENTAS (con factura / sin factura, contado / crédito)
  // ============================================================
  ventas() { return this._get(this.KEYS.ventas, []); },
  guardarVentas(lista) { this._set(this.KEYS.ventas, lista); },
  buscarVentaPorId(id) { return this.ventas().find(v => v.id === Number(id)); },

  /**
   * cabecera: { tipo:'CON_FACTURA'|'SIN_FACTURA', modalidad:'CONTADO'|'CREDITO', fecha, clienteNombre, descuento, metodo, idProformaOrigen }
   * items: [{ id_producto, codigo, descripcion, cantidad, precio }]
   * Efectos reales: descuenta stock, crea comprobante de INGRESO.
   */
  crearVenta(cabecera, items) {
    const subtotal = items.reduce((s, it) => s + it.cantidad * it.precio, 0);
    const descuento = Number(cabecera.descuento || 0);
    const total = subtotal - descuento;
    const prefijo = cabecera.tipo === 'CON_FACTURA' ? 'FV-' : 'NV-';
    const numero = cabecera.numero || this.siguienteNumeroDocumento(prefijo);

    const l = this.ventas();
    const venta = {
      id: l.length ? Math.max(...l.map(v => v.id)) + 1 : 1,
      numero, tipo: cabecera.tipo, modalidad: cabecera.modalidad || 'CONTADO',
      fecha: cabecera.fecha, clienteNombre: cabecera.clienteNombre,
      idProformaOrigen: cabecera.idProformaOrigen || null,
      items, subtotal, descuento, total, estado: 'ACTIVA',
    };
    l.push(venta);
    this.guardarVentas(l);

    // Descuenta stock real
    items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, -it.cantidad); });

    // Comprobante de INGRESO automático (dinero que entra) — si es crédito, se anota igual como referencia de la deuda
    const comp = this.crearComprobante({
      tipo: 'INGRESO',
      concepto: `Venta ${numero} — ${cabecera.clienteNombre}${cabecera.modalidad === 'CREDITO' ? ' (crédito)' : ''}`,
      entidad: cabecera.clienteNombre,
      monto: total,
      referencia: numero,
      metodo: cabecera.modalidad === 'CREDITO' ? 'Crédito (pendiente de cobro)' : cabecera.metodo,
      fecha: cabecera.fecha,
    });
    venta.comprobante = comp.numero;
    this.guardarVentas(l.map(v => v.id === venta.id ? venta : v));

    return venta;
  },

  anularVenta(id) {
    const venta = this.buscarVentaPorId(id);
    if (!venta || venta.estado === 'ANULADA') return false;
    venta.items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, it.cantidad); }); // revierte stock
    const l = this.ventas().map(v => v.id === venta.id ? { ...v, estado: 'ANULADA' } : v);
    this.guardarVentas(l);
    return true;
  },

  /**
   * Edita una venta existente. Si trae items (venta manual), revierte el stock que
   * había descontado la versión anterior y aplica el de la nueva. Si no trae items
   * (venta importada del SIAT, sin detalle de productos), solo actualiza cabecera/montos.
   */
  actualizarVenta(id, cabecera, items) {
    const l = this.ventas();
    const i = l.findIndex(v => v.id === Number(id));
    if (i < 0) return null;
    const original = l[i];

    if (items) {
      // Revertir stock de la versión anterior (se había descontado, ahora se devuelve)
      original.items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, it.cantidad); });

      const subtotal = items.reduce((s, it) => s + it.cantidad * it.precio, 0);
      const descuento = Number(cabecera.descuento || 0);
      const total = subtotal - descuento;

      l[i] = { ...original, tipo: cabecera.tipo, modalidad: cabecera.modalidad, fecha: cabecera.fecha, clienteNombre: cabecera.clienteNombre, items, subtotal, descuento, total };

      // Aplicar stock de la versión nueva (se descuenta de nuevo)
      items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, -it.cantidad); });
    } else {
      l[i] = { ...original, ...cabecera, total: cabecera.total !== undefined ? cabecera.total : original.total };
    }

    this.guardarVentas(l);

    if (l[i].comprobante) {
      const comp = this.comprobantes().find(c => c.numero === l[i].comprobante);
      if (comp) this.actualizarComprobante(comp.id, { monto: l[i].total, concepto: `Venta ${l[i].numero} — ${l[i].clienteNombre}`, fecha: l[i].fecha });
    }
    return l[i];
  },

  // Elimina una venta por completo: revuelve el stock que se había descontado y borra su comprobante de ingreso.
  eliminarVenta(id) {
    const venta = this.buscarVentaPorId(id);
    if (!venta) return false;
    if (venta.estado === 'ACTIVA') {
      venta.items.forEach(it => { if (it.id_producto) this.ajustarStock(it.id_producto, it.cantidad); });
    }
    if (venta.comprobante) {
      const comp = this.comprobantes().find(c => c.numero === venta.comprobante);
      if (comp) this.eliminarComprobante(comp.id);
    }
    this.guardarVentas(this.ventas().filter(v => v.id !== Number(id)));
    return true;
  },

  // ============================================================
  // IMPORTACIÓN CSV (compras y ventas históricas)
  // ============================================================
  // Parser CSV simple: soporta comas y comillas dobles.
  parsearCSV(texto) {
    const filas = [];
    let fila = [], campo = '', dentroComillas = false;
    for (let i = 0; i < texto.length; i++) {
      const c = texto[i];
      if (dentroComillas) {
        if (c === '"' && texto[i + 1] === '"') { campo += '"'; i++; }
        else if (c === '"') { dentroComillas = false; }
        else { campo += c; }
      } else {
        if (c === '"') dentroComillas = true;
        else if (c === ',') { fila.push(campo); campo = ''; }
        else if (c === '\n' || c === '\r') {
          if (c === '\r' && texto[i + 1] === '\n') i++;
          fila.push(campo); campo = '';
          if (fila.length > 1 || fila[0] !== '') filas.push(fila);
          fila = [];
        } else { campo += c; }
      }
    }
    if (campo !== '' || fila.length) { fila.push(campo); filas.push(fila); }

    const encabezados = (filas.shift() || []).map(h => h.trim());
    return filas.map(f => {
      const obj = {};
      encabezados.forEach((h, i) => obj[h] = (f[i] || '').trim());
      return obj;
    });
  },

  // Agrupa filas de CSV por número de documento y crea una compra por grupo.
  // Columnas esperadas: Numero,Fecha,Proveedor,Tipo,Codigo,Descripcion,Cantidad,Costo,Descuento
  importarComprasCSV(filas) {
    const grupos = {};
    filas.forEach(f => {
      const num = f.Numero || f.numero || ('SIN-NUM-' + Math.random());
      if (!grupos[num]) grupos[num] = [];
      grupos[num].push(f);
    });

    let creadas = 0, errores = [];
    Object.entries(grupos).forEach(([numero, lineas]) => {
      try {
        const primera = lineas[0];
        const tipo = (primera.Tipo || '').toUpperCase().includes('SIN') ? 'SIN_FACTURA' : 'CON_FACTURA';
        const proveedor = this.obtenerOCrearProveedorPorNombre(primera.Proveedor);

        const items = lineas.map(l => {
          const codigo = (l.Codigo || '').trim();
          let producto = this.productos().find(p => p.codigo.toLowerCase() === codigo.toLowerCase());
          if (!producto && codigo) {
            producto = this.crearProducto({
              codigo, descripcion: l.Descripcion || codigo, marca: '', unidad: 'PZA',
              costo: parseFloat(l.Costo) || 0, precio: (parseFloat(l.Costo) || 0) * 1.3,
              stock: 0, stockMin: 0,
            });
          }
          return {
            id_producto: producto ? producto.id : null,
            codigo, descripcion: l.Descripcion || codigo,
            cantidad: parseFloat(l.Cantidad) || 0,
            costo: parseFloat(l.Costo) || 0,
          };
        });

        this.crearCompra({
          numero, tipo, fecha: primera.Fecha || new Date().toISOString().slice(0, 10),
          proveedorNombre: proveedor.nombre, descuento: parseFloat(primera.Descuento) || 0,
          metodo: 'Importado CSV',
        }, items);
        creadas++;
      } catch (e) {
        errores.push('Documento ' + numero + ': ' + e.message);
      }
    });
    return { creadas, errores };
  },

  // Columnas esperadas: Numero,Fecha,Cliente,Tipo,Modalidad,Codigo,Descripcion,Cantidad,Precio,Descuento
  importarVentasCSV(filas) {
    const grupos = {};
    filas.forEach(f => {
      const num = f.Numero || f.numero || ('SIN-NUM-' + Math.random());
      if (!grupos[num]) grupos[num] = [];
      grupos[num].push(f);
    });

    let creadas = 0, errores = [];
    Object.entries(grupos).forEach(([numero, lineas]) => {
      try {
        const primera = lineas[0];
        const tipo = (primera.Tipo || '').toUpperCase().includes('SIN') ? 'SIN_FACTURA' : 'CON_FACTURA';
        const modalidad = (primera.Modalidad || '').toUpperCase().includes('CRED') ? 'CREDITO' : 'CONTADO';
        const nombreCliente = (primera.Cliente || 'Cliente sin nombre').trim();
        let cliente = this.clientes().find(c => c.nombre.toLowerCase() === nombreCliente.toLowerCase());
        if (!cliente) cliente = this.crearCliente({ nombre: nombreCliente, nit: '', telefono: '', correo: '', contacto: '' });

        const items = lineas.map(l => {
          const codigo = (l.Codigo || '').trim();
          const producto = this.productos().find(p => p.codigo.toLowerCase() === codigo.toLowerCase());
          return {
            id_producto: producto ? producto.id : null,
            codigo, descripcion: l.Descripcion || codigo,
            cantidad: parseFloat(l.Cantidad) || 0,
            precio: parseFloat(l.Precio) || 0,
          };
        });

        this.crearVenta({
          numero, tipo, modalidad, fecha: primera.Fecha || new Date().toISOString().slice(0, 10),
          clienteNombre: cliente.nombre, descuento: parseFloat(primera.Descuento) || 0,
          metodo: 'Importado CSV',
        }, items);
        creadas++;
      } catch (e) {
        errores.push('Documento ' + numero + ': ' + e.message);
      }
    });
    return { creadas, errores };
  },

  // ---- Datos de ejemplo (se cargan solo la primera vez) ----
  sembrarSiVacio() {
    if (this.productos().length === 0) {
      this.guardarProductos([
        { id: 1, codigo: 'LF9009', oem: 'LF9009', equivalente: 'P550949', descripcion: 'Filtro de aceite Fleetguard para motor Cummins', marca: 'Fleetguard', unidad: 'PZA', costo: 45.00, precio: 89.90, stock: 50, stockMin: 10, activo: true },
        { id: 2, codigo: 'FF5320', oem: '33966', equivalente: '33966', descripcion: 'Filtro de combustible Fleetguard', marca: 'Fleetguard', unidad: 'PZA', costo: 38.00, precio: 72.50, stock: 18, stockMin: 8, activo: true },
        { id: 3, codigo: 'VAL-15W40', oem: '', equivalente: '', descripcion: 'Aceite Valvoline Premium Blue 15W-40, bidón 20L', marca: 'Valvoline', unidad: 'BID', costo: 310.00, precio: 459.90, stock: 32, stockMin: 6, activo: true },
        { id: 4, codigo: 'AF25550', oem: 'C27883', equivalente: 'C27883', descripcion: 'Filtro de aire Fleetguard para Howo / Shacman', marca: 'Fleetguard', unidad: 'PZA', costo: 62.00, precio: 115.00, stock: 4, stockMin: 10, activo: true },
      ]);
    }
    if (this.clientes().length === 0) {
      this.guardarClientes([
        { id: 1, nombre: 'Transportes Santa Cruz SRL', nit: '1234567021', telefono: '70011122', direccion: 'Av. Cristo Redentor km 5', correo: 'contacto@transportessc.com', contacto: 'Juan Pérez' },
        { id: 2, nombre: 'Flota Dongfeng Bolivia', nit: '9876543012', telefono: '76543210', direccion: '', correo: '', contacto: '' },
        { id: 3, nombre: 'Taller Los Andes', nit: '1122334455', telefono: '69874521', direccion: '', correo: '', contacto: '' },
      ]);
    }
    if (this.proveedores().length === 0) {
      this.guardarProveedores([
        { id: 1, nombre: 'Fleetguard Bolivia SRL', nit: '9998887771', telefono: '33221100' },
        { id: 2, nombre: 'Valvoline Import SRL', nit: '5554443332', telefono: '77889900' },
      ]);
    }
  },
};

DB.sembrarSiVacio();

// ---- Utilidades compartidas de UI ----
function formatoMoneda(n) {
  // Formato consistente con la versión PHP del sistema: punto decimal, coma de miles (ej. 1,234.56)
  const numero = Number(n || 0);
  const partes = numero.toFixed(2).split('.');
  partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return partes.join('.');
}

// ---- Conversor de número a letras (para "monto en letras" en comprobantes formales) ----
function _numeroEnteroATexto(n) {
  if (n === 0) return 'cero';
  const unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
  const especiales10a19 = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
  const decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
  const centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

  function bloqueHasta999(num) {
    if (num === 0) return '';
    if (num === 100) return 'cien';
    let texto = '';
    const c = Math.floor(num / 100);
    const resto = num % 100;
    if (c > 0) texto += centenas[c] + ' ';
    if (resto > 0) {
      if (resto < 10) texto += unidades[resto];
      else if (resto < 20) texto += especiales10a19[resto - 10];
      else {
        const d = Math.floor(resto / 10), u = resto % 10;
        if (d === 2 && u > 0) texto += 'veinti' + unidades[u];
        else { texto += decenas[d]; if (u > 0) texto += ' y ' + unidades[u]; }
      }
    }
    return texto.trim();
  }

  let resultado = '';
  let resto = Math.floor(n);

  const millones = Math.floor(resto / 1000000);
  resto %= 1000000;
  if (millones > 0) resultado += (millones === 1 ? 'un millón' : bloqueHasta999(millones) + ' millones') + ' ';

  const miles = Math.floor(resto / 1000);
  resto %= 1000;
  if (miles > 0) resultado += (miles === 1 ? 'mil' : bloqueHasta999(miles) + ' mil') + ' ';

  if (resto > 0) resultado += bloqueHasta999(resto);

  return resultado.trim();
}

// Ej: montoALetras(1718.56) -> "Mil setecientos dieciocho 56/100 bolivianos"
function montoALetras(monto) {
  const entero = Math.floor(Math.abs(monto));
  const centavos = Math.round((Math.abs(monto) - entero) * 100);
  const texto = _numeroEnteroATexto(entero);
  const capitalizado = texto.charAt(0).toUpperCase() + texto.slice(1);
  return `${capitalizado} ${String(centavos).padStart(2, '0')}/100 bolivianos`;
}

function mostrarToast(mensaje, tipo) {
  let toast = document.getElementById('toastGiseca');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toastGiseca';
    toast.className = 'toast-giseca';
    document.body.appendChild(toast);
  }
  toast.textContent = mensaje;
  toast.className = 'toast-giseca mostrar ' + (tipo || '');
  setTimeout(() => toast.classList.remove('mostrar'), 2800);
}

// ---- Guardia de sesión: proteger páginas internas ----
function exigirSesion() {
  if (!DB.haySesion()) {
    window.location.href = 'login.html';
  }
}
