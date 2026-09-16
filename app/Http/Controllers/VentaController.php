<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\ComprobanteService;
use App\Services\ContadorService;
use App\Services\SiatCsvService;
use App\Services\StockService;
use App\Services\SucursalContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venta::with(['cliente', 'facturaElectronica'])->orderByDesc('id');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->get('tipo'));
        }
        if ($request->filled('modalidad')) {
            $query->where('modalidad', $request->get('modalidad'));
        }
        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->get('sucursal_id'));
        }
        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%")
                    ->orWhere('numero_factura_siat', 'like', "%{$q}%");
            });
        }

        $ventas = $query->paginate(20)->withQueryString();

        $activas = Venta::where('estado', 'activa');
        if ($request->filled('sucursal_id')) {
            $activas->where('sucursal_id', $request->get('sucursal_id'));
        }
        $kpis = [
            'total' => (float) (clone $activas)->sum('total'),
            'contado' => (float) (clone $activas)->where('modalidad', 'contado')->sum('total'),
            'credito' => (float) (clone $activas)->where('modalidad', 'credito')->sum('total'),
            'debito_fiscal' => (float) (clone $activas)->where('origen_siat', true)->sum('debito_fiscal'),
            'documentos' => (clone $activas)->count(),
        ];

        return view('ventas.index', [
            'ventas' => $ventas,
            'kpis' => $kpis,
            'tipo' => $request->get('tipo', ''),
            'modalidad' => $request->get('modalidad', ''),
            'sucursal_id' => $request->get('sucursal_id', ''),
            'sucursales' => Sucursal::activas()->orderBy('codigo')->get(),
            'q' => $request->get('q', ''),
        ]);
    }

    public function create()
    {
        return view('ventas.create', [
            'clientes' => Cliente::orderBy('nombre')->get(),
            'fechaHoy' => date('Y-m-d'),
        ]);
    }

    protected function reglasItems(): array
    {
        return [
            'cliente_id' => 'nullable|exists:clientes,id',
            'cliente_nuevo' => 'nullable|string|max:255',
            'tipo' => 'required|in:con_factura,sin_factura',
            'modalidad' => 'required|in:contado,credito',
            'fecha' => 'required|date',
            'descuento' => 'nullable|numeric|min:0',
            'metodo' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio' => 'required|numeric|min:0',
        ];
    }

    protected function resolverCliente(Request $request): Cliente
    {
        $nuevo = trim($request->input('cliente_nuevo', ''));
        if ($nuevo !== '') {
            $existente = Cliente::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nuevo)])->first();

            return $existente ?? Cliente::create(['nombre' => $nuevo]);
        }

        $cliente = Cliente::find($request->input('cliente_id'));
        abort_if(! $cliente, 422, 'Selecciona o escribe un cliente.');

        return $cliente;
    }

    public function store(
        Request $request,
        ContadorService $contadores,
        StockService $stock,
        ComprobanteService $comprobantes
    ) {
        $data = $request->validate($this->reglasItems());

        $cliente = $this->resolverCliente($request);

        try {

            $venta = DB::transaction(function () use (
                $data,
                $request,
                $cliente,
                $contadores,
                $stock,
                $comprobantes
            ) {

                $subtotal = 0;
                $detalles = [];

                foreach ($data['items'] as $it) {

                    $producto = Producto::whereKey($it['producto_id'])->lockForUpdate()->firstOrFail();

                    $cantidad = round((float) $it['cantidad'], 2);
                    $precio = round((float) $it['precio'], 2);

                    $sub = round($cantidad * $precio, 2);

                    $subtotal = round($subtotal + $sub, 2);

                    $detalles[] = [
                        'producto' => $producto,
                        'cantidad' => $cantidad,
                        'precio' => $precio,
                        'sub' => $sub,
                    ];
                }

                $descuento = round((float) ($data['descuento'] ?? 0), 2);

                $total = max(
                    0,
                    round($subtotal - $descuento, 2)
                );

                $numero = $contadores->siguienteUnico(
                    $data['tipo'] === 'con_factura'
                        ? 'FV-'
                        : 'NV-',
                    fn ($n) => Venta::withTrashed()
                        ->where('numero', $n)
                        ->exists()
                );

                $sucursalActual = SucursalContext::sucursal();

                $puntoVentaActual = SucursalContext::puntoVenta();

                $venta = Venta::create([

                    'numero' => $numero,

                    'tipo' => $data['tipo'],

                    'modalidad' => $data['modalidad'],

                    'cliente_id' => $cliente->id,

                    'cliente_nombre' => $cliente->nombre,

                    'fecha' => $data['fecha'],

                    'subtotal' => $subtotal,

                    'descuento' => $descuento,

                    'total' => $total,

                    'base_df' => $data['tipo'] === 'con_factura'
                        ? $total
                        : null,

                    'debito_fiscal' => $data['tipo'] === 'con_factura'
                        ? round($total * 0.13, 2)
                        : null,

                    'observaciones' => $request->input('observaciones'),

                    'sucursal_id' => $sucursalActual->id,

                    'punto_venta_id' => $puntoVentaActual->id,

                    'codigo_sucursal' => (int) $sucursalActual->codigo,

                    'codigo_punto_venta' => (int) $puntoVentaActual->codigo,

                ]);

                foreach ($detalles as $d) {

                    $venta->detalles()->create([

                        'producto_id' => $d['producto']->id,

                        'codigo_interno' => $d['producto']->codigo_interno,

                        'codigo_producto' => $d['producto']->codigo,

                        'descripcion_producto' => $d['producto']->descripcion,

                        'cantidad' => $d['cantidad'],

                        'precio_unitario' => $d['precio'],

                        'subtotal' => $d['sub'],

                    ]);

                    // Descontar inventario

                    $stock->disminuirStock(
                        $d['producto']->fresh(),
                        $d['cantidad']
                    );
                }

                // Crear comprobante solamente una vez

                $comp = $comprobantes->crearParaVenta(
                    $venta->fresh(),
                    $data['metodo'] ?? 'Efectivo'
                );

                return [
                    'venta' => $venta,
                    'comprobante' => $comp,
                ];
            }); // cierre correcto DB::transaction

        } catch (\InvalidArgumentException $e) {

            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('ventas.index')
            ->with(
                'exito',
                "Venta {$venta['venta']->numero} guardada. Comprobante {$venta['comprobante']->numero} generado."
            );
    }

    public function edit(Venta $venta)
    {
        $venta->load('detalles.producto');

        if ($venta->origen_siat) {
            return view('ventas.edit-siat', compact('venta'));
        }

        return view('ventas.edit', [
            'venta' => $venta,
            'clientes' => Cliente::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Venta $venta, StockService $stock)
    {
        if ($venta->origen_siat) {
            $data = $request->validate([
                'cliente_nombre' => 'required|string|max:255',
                'fecha' => 'required|date',
                'numero_factura_siat' => 'nullable|string|max:100',
                'total' => 'required|numeric|min:0',
                'base_df' => 'nullable|numeric|min:0',
                'debito_fiscal' => 'nullable|numeric|min:0',
            ]);

            DB::transaction(function () use ($venta, $data) {
                $venta->update([
                    'cliente_nombre' => $data['cliente_nombre'],
                    'fecha' => $data['fecha'],
                    'numero_factura_siat' => $data['numero_factura_siat'] ?? null,
                    'total' => $data['total'],
                    'subtotal' => $data['total'],
                    'base_df' => $data['base_df'] ?? null,
                    'debito_fiscal' => $data['debito_fiscal'] ?? null,
                ]);
                $this->sincronizarComprobante($venta->fresh());
            });

            return redirect()->route('ventas.index')->with('exito', 'Venta SIAT actualizada');
        }

        if ($venta->estado === 'anulada') {
            return back()->with('error', 'No se puede editar una venta anulada.');
        }

        $data = $request->validate($this->reglasItems());
        $cliente = $this->resolverCliente($request);

        try {
            DB::transaction(function () use ($data, $request, $venta, $cliente, $stock) {
                $venta->load('detalles.producto');
                foreach ($venta->detalles as $det) {
                    if ($det->producto) {
                        $stock->revertirStock($det->producto, (float) $det->cantidad, 'venta');
                    }
                }
                $venta->detalles()->delete();

                $subtotal = 0;
                $detalles = [];
                foreach ($data['items'] as $it) {
                    $producto = Producto::whereKey($it['producto_id'])->lockForUpdate()->firstOrFail();
                    $cantidad = round((float) $it['cantidad'], 2);
                    $precio = round((float) $it['precio'], 2);
                    $sub = round($cantidad * $precio, 2);
                    $subtotal = round($subtotal + $sub, 2);
                    $detalles[] = compact('producto', 'cantidad', 'precio', 'sub');
                }
                $descuento = round((float) ($data['descuento'] ?? 0), 2);
                $total = max(0, round($subtotal - $descuento, 2));

                $venta->update([
                    'tipo' => $data['tipo'],
                    'modalidad' => $data['modalidad'],
                    'cliente_id' => $cliente->id,
                    'cliente_nombre' => $cliente->nombre,
                    'fecha' => $data['fecha'],
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'total' => $total,
                    'base_df' => $data['tipo'] === 'con_factura' ? $total : null,
                    'debito_fiscal' => $data['tipo'] === 'con_factura' ? round($total * 0.13, 2) : null,
                    'observaciones' => $request->input('observaciones'),
                ]);

                foreach ($detalles as $d) {
                    $venta->detalles()->create([
                        'producto_id' => $d['producto']->id,

                        'codigo_interno' => $d['producto']->codigo_interno,

                        'codigo_producto' => $d['producto']->codigo,

                        'descripcion_producto' => $d['producto']->descripcion,

                        'cantidad' => $d['cantidad'],

                        'precio_unitario' => $d['precio'],

                        'subtotal' => $d['sub'],
                    ]);
                    $stock->disminuirStock($d['producto']->fresh(), $d['cantidad']);
                }

                $this->sincronizarComprobante($venta->fresh());
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('ventas.index')->with('exito', 'Venta actualizada');
    }

    protected function sincronizarComprobante(Venta $venta): void
    {
        $comp = Comprobante::where('origen_venta_id', $venta->id)->first();
        if (! $comp) {
            return;
        }
        $comp->update([
            'monto' => $venta->total,
            'entidad' => $venta->cliente_nombre,
            'fecha' => $venta->fecha,
        ]);
        if ($comp->pagos()->count() === 1) {
            $comp->pagos()->update(['monto' => $venta->total]);
        }
    }

    public function anular(Venta $venta, StockService $stock)
    {
        if ($venta->estado === 'anulada') {
            return back()->with('error', 'La venta ya está anulada.');
        }

        DB::transaction(function () use ($venta, $stock) {
            $venta->load('detalles.producto');
            if (! $venta->origen_siat) {
                foreach ($venta->detalles as $det) {
                    if ($det->producto) {
                        $stock->revertirStock($det->producto, (float) $det->cantidad, 'venta');
                    }
                }
            }
            $venta->update(['estado' => 'anulada']);
        });

        return back()->with('exito', 'Venta anulada, stock revertido');
    }

    public function destroy(Venta $venta, StockService $stock)
    {
        DB::transaction(function () use ($venta, $stock) {
            $venta->load('detalles.producto');
            if ($venta->estado === 'activa' && ! $venta->origen_siat) {
                foreach ($venta->detalles as $det) {
                    if ($det->producto) {
                        $stock->revertirStock($det->producto, (float) $det->cantidad, 'venta');
                    }
                }
            }
            Comprobante::where('origen_venta_id', $venta->id)->delete();
            $venta->delete();
        });

        return redirect()->route('ventas.index')->with('exito', 'Venta eliminada');
    }

    // ---------- Importaciones ----------

    /**
     * Formato genérico CSV: Numero,Fecha,Cliente,Tipo,Modalidad,Codigo,Descripcion,Cantidad,Precio,Descuento
     * Si detecta encabezados SIAT, aplica flujo SIAT.
     */
    public function importarCsv(Request $request, ContadorService $contadores, StockService $stock, ComprobanteService $comprobantes)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:5120']);
        $texto = file_get_contents($request->file('archivo')->getRealPath());
        $filas = SiatCsvService::parsearCSV($texto);

        if (empty($filas)) {
            return back()->with('error', 'El archivo no tiene filas de datos.');
        }

        if (SiatCsvService::pareceSiat($filas)) {
            $res = $this->importarSiatFilas($filas, $contadores, $comprobantes);
        } else {
            $res = $this->importarGenericoFilas($filas, $contadores, $stock, $comprobantes);
            if (! empty($res['bloqueado_stock'])) {
                return back()->with('error', $res['bloqueado_stock']);
            }
        }

        return $this->respuestaImportacion($request, $res, 'venta(s)');
    }

    public function importarSiat(Request $request, ContadorService $contadores, ComprobanteService $comprobantes)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:5120']);
        $texto = file_get_contents($request->file('archivo')->getRealPath());
        $filas = SiatCsvService::parsearCSV($texto);
        $res = $this->importarSiatFilas($filas, $contadores, $comprobantes);

        return $this->respuestaImportacion($request, $res, 'factura(s) del SIAT');
    }

    public function importarExcel(Request $request, ContadorService $contadores, StockService $stock, ComprobanteService $comprobantes)
    {
        $data = $request->validate(['filas' => 'required|array|min:1']);
        $res = $this->importarGenericoFilas($data['filas'], $contadores, $stock, $comprobantes);
        if (! empty($res['bloqueado_stock'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $res['bloqueado_stock']], 422);
            }

            return back()->with('error', $res['bloqueado_stock']);
        }

        return $this->respuestaImportacion($request, $res, 'venta(s)');
    }

    protected function respuestaImportacion(Request $request, array $res, string $unidad)
    {
        $mensaje = "{$res['creadas']} {$unidad} importada(s).";
        if (! empty($res['duplicadas'])) {
            $mensaje .= " {$res['duplicadas']} duplicada(s) omitida(s).";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'creadas' => $res['creadas'],
                'duplicadas' => $res['duplicadas'],
                'errores' => $res['errores'],
                'message' => $mensaje,
            ]);
        }

        return back()->with('exito', $mensaje);
    }

    protected function importarSiatFilas(array $filas, ContadorService $contadores, ComprobanteService $comprobantes): array
    {
        $creadas = 0;
        $duplicadas = 0;
        $errores = [];

        foreach ($filas as $i => $fila) {
            try {
                $codigoAutorizacion = SiatCsvService::obtenerCampo($fila, 'CODIGO DE AUTORIZACION');
                if ($codigoAutorizacion === '') {
                    continue;
                }
                if (Venta::where('codigo_autorizacion', $codigoAutorizacion)->exists()) {
                    $duplicadas++;

                    continue;
                }

                $nit = SiatCsvService::obtenerCampo($fila, 'NIT / CI CLIENTE', 'NIT/CI CLIENTE', 'NIT CLIENTE');
                $razon = SiatCsvService::obtenerCampo($fila, 'RAZON SOCIAL / NOMBRE', 'RAZON SOCIAL', 'NOMBRE O RAZON SOCIAL') ?: 'Cliente SIAT s/n';
                $cliente = $nit !== ''
                    ? Cliente::firstOrCreate(['nit' => $nit], ['nombre' => $razon])
                    : Cliente::firstOrCreate(['nombre' => $razon]);

                $esAnulada = mb_strtoupper(SiatCsvService::obtenerCampo($fila, 'ESTADO')) === 'ANULADA';
                $total = (float) (SiatCsvService::obtenerCampo($fila, 'IMPORTE TOTAL DE LA VENTA', 'IMPORTE TOTAL VENTA') ?: 0);
                $baseDF = (float) (SiatCsvService::obtenerCampo($fila, 'IMPORTE BASE PARA DEBITO FISCAL', 'IMPORTE BASE DF') ?: 0);
                $debito = (float) (SiatCsvService::obtenerCampo($fila, 'DEBITO FISCAL') ?: 0);

                DB::transaction(function () use ($fila, $nit, $cliente, $esAnulada, $total, $baseDF, $debito, $codigoAutorizacion, $contadores, $comprobantes) {
                    $numero = $contadores->siguienteUnico('FV-', fn ($n) => Venta::withTrashed()->where('numero', $n)->exists());
                    $v = Venta::create([
                        'numero' => $numero,
                        'tipo' => 'con_factura',
                        'modalidad' => 'contado',
                        'cliente_id' => $cliente->id,
                        'cliente_nombre' => $cliente->nombre,
                        'fecha' => SiatCsvService::fechaSiatAiso(SiatCsvService::obtenerCampo($fila, 'FECHA DE LA FACTURA', 'FECHA DE FACTURA')),
                        'subtotal' => $total,
                        'descuento' => 0,
                        'total' => $total,
                        'base_df' => $esAnulada ? 0 : $baseDF,
                        'debito_fiscal' => $esAnulada ? 0 : $debito,
                        'estado' => $esAnulada ? 'anulada' : 'activa',
                        'origen_siat' => true,
                        'codigo_autorizacion' => $codigoAutorizacion,
                        'numero_factura_siat' => SiatCsvService::obtenerCampo($fila, 'Nº DE LA FACTURA', 'NUMERO FACTURA', 'N° DE LA FACTURA', 'NRO. DE LA FACTURA', 'NRO DE LA FACTURA'),
                        'nit_cliente' => $nit,
                    ]);

                    if (! $esAnulada) {
                        $comprobantes->crearParaVenta($v, 'Registro SIAT');
                    }
                });

                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = 'Fila '.($i + 2).': '.$e->getMessage();
            }
        }

        return compact('creadas', 'duplicadas', 'errores');
    }

    protected function importarGenericoFilas(array $filas, ContadorService $contadores, StockService $stock, ComprobanteService $comprobantes): array
    {
        $grupos = [];
        foreach ($filas as $f) {
            if (! is_array($f)) {
                continue;
            }
            $num = trim((string) ($f['Numero'] ?? $f['numero'] ?? ''));
            $grupos[$num ?: '__SIN_NUM__'][] = $f;
        }

        $creadas = 0;
        $duplicadas = 0;
        $errores = [];

        foreach ($grupos as $numeroDoc => $lineas) {
            try {
                $numeroDoc = $numeroDoc === '__SIN_NUM__' ? null : $numeroDoc;
                if ($numeroDoc && Venta::where('numero', $numeroDoc)->exists()) {
                    $duplicadas++;

                    continue;
                }

                $primera = $lineas[0];
                $tipoRaw = mb_strtoupper(trim((string) ($primera['Tipo'] ?? $primera['tipo'] ?? 'SIN_FACTURA')));
                $tipo = str_contains($tipoRaw, 'SIN') ? 'sin_factura' : 'con_factura';
                $modRaw = mb_strtoupper(trim((string) ($primera['Modalidad'] ?? $primera['modalidad'] ?? 'CONTADO')));
                $modalidad = str_contains($modRaw, 'CRED') ? 'credito' : 'contado';
                $nombreCli = trim((string) ($primera['Cliente'] ?? $primera['cliente'] ?? 'Cliente sin nombre')) ?: 'Cliente sin nombre';
                $cliente = Cliente::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreCli)])->first()
                    ?? Cliente::create(['nombre' => $nombreCli]);

                $items = [];
                foreach ($lineas as $l) {
                    $codigo = trim((string) ($l['Codigo'] ?? $l['codigo'] ?? ''));
                    if ($codigo === '') {
                        continue;
                    }
                    $producto = Producto::where(function ($q) use ($codigo) {

                        $q->whereRaw(
                            'LOWER(codigo) = ?',
                            [mb_strtolower($codigo)]
                        )
                            ->orWhereRaw(
                                'LOWER(codigo_interno) = ?',
                                [mb_strtolower($codigo)]
                            );
                    })->first();
                    if (! $producto) {
                        throw new \RuntimeException("Producto {$codigo} no existe; créalo en Inventario antes de importar.");
                    }
                    $items[] = [
                        'producto' => $producto,
                        'cantidad' => (float) ($l['Cantidad'] ?? $l['cantidad'] ?? 0),
                        'precio' => (float) ($l['Precio'] ?? $l['precio'] ?? 0),
                    ];
                }

                if (empty($items)) {
                    throw new \RuntimeException("Documento {$numeroDoc}: sin items válidos.");
                }

                DB::transaction(function () use ($numeroDoc, $tipo, $modalidad, $primera, $cliente, $items, $contadores, $stock, $comprobantes) {
                    // Bloquear filas de producto y validar stock dentro de la
                    // transacción (evita TOCTOU entre la comprobación y el descuento).
                    $bloqueados = Producto::whereIn('id', collect($items)->map(fn ($it) => $it['producto']->id)->all())
                        ->lockForUpdate()->get()->keyBy('id');
                    foreach ($items as $it) {
                        $disp = (float) ($bloqueados[$it['producto']->id]->stock ?? 0);
                        if ($disp < $it['cantidad']) {
                            throw new \RuntimeException(
                                "Documento {$numeroDoc}: stock insuficiente para {$it['producto']->codigo} (disp. {$disp}, req. {$it['cantidad']})."
                            );
                        }
                    }

                    $subtotal = round(array_sum(array_map(fn ($it) => $it['cantidad'] * $it['precio'], $items)), 2);
                    $descuento = round((float) ($primera['Descuento'] ?? $primera['descuento'] ?? 0), 2);
                    $total = max(0, round($subtotal - $descuento, 2));

                    $venta = Venta::create([
                        'numero' => $numeroDoc ?? $contadores->siguienteUnico($tipo === 'con_factura' ? 'FV-' : 'NV-', fn ($n) => Venta::withTrashed()->where('numero', $n)->exists()),
                        'tipo' => $tipo,
                        'modalidad' => $modalidad,
                        'cliente_id' => $cliente->id,
                        'cliente_nombre' => $cliente->nombre,
                        'fecha' => trim((string) ($primera['Fecha'] ?? $primera['fecha'] ?? '')) ?: date('Y-m-d'),
                        'subtotal' => $subtotal,
                        'descuento' => $descuento,
                        'total' => $total,
                        'base_df' => $tipo === 'con_factura' ? $total : null,
                        'debito_fiscal' => $tipo === 'con_factura' ? round($total * 0.13, 2) : null,
                    ]);

                    foreach ($items as $it) {
                        $sub = round($it['cantidad'] * $it['precio'], 2);
                        $venta->detalles()->create([
                            'producto_id' => $it['producto']->id,
                            'codigo_interno' => $it['producto']->codigo_interno,
                            'codigo_producto' => $it['producto']->codigo,
                            'descripcion_producto' => $it['producto']->descripcion,
                            'cantidad' => $it['cantidad'],
                            'precio_unitario' => $it['precio'],
                            'subtotal' => $sub,
                        ]);
                        $stock->disminuirStock($it['producto']->fresh(), $it['cantidad']);
                    }

                    $comprobantes->crearParaVenta($venta->fresh(), 'Importado CSV');
                });

                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = 'Documento '.$numeroDoc.': '.$e->getMessage();
            }
        }

        return compact('creadas', 'duplicadas', 'errores');
    }
}
