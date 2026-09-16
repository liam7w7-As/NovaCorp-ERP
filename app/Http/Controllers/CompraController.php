<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\ComprobanteService;
use App\Services\ContadorService;
use App\Services\SiatCsvService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with('proveedor')->orderByDesc('id');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->get('tipo'));
        }
        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('proveedor_nombre', 'like', "%{$q}%")
                    ->orWhere('numero_factura_siat', 'like', "%{$q}%");
            });
        }

        $compras = $query->paginate(20)->withQueryString();

        $todas = Compra::query();
        $kpis = [
            'total' => (float) (clone $todas)->sum('total'),
            'credito_fiscal' => (float) Compra::where('origen_siat', true)->sum('credito_fiscal'),
            'con_factura' => (clone $todas)->where('tipo', 'con_factura')->count(),
            'sin_factura' => (clone $todas)->where('tipo', 'sin_factura')->count(),
        ];

        return view('compras.index', [
            'compras' => $compras,
            'kpis' => $kpis,
            'tipo' => $request->get('tipo', ''),
            'q' => $request->get('q', ''),
        ]);
    }

    public function create()
    {
        return view('compras.create', [
            'proveedores' => Proveedor::orderBy('nombre')->get(),
            'fechaHoy' => date('Y-m-d'),
        ]);
    }

    protected function reglasItems(): array
    {
        return [
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'proveedor_nuevo' => 'nullable|string|max:255',
            'tipo' => 'required|in:con_factura,sin_factura',
            'modalidad' => 'required|in:contado,credito',
            'fecha' => 'required|date',
            'descuento' => 'nullable|numeric|min:0',
            'metodo' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.costo' => 'required|numeric|min:0',
        ];
    }

    protected function resolverProveedor(Request $request): Proveedor
    {
        $nuevo = trim($request->input('proveedor_nuevo', ''));
        if ($nuevo !== '') {
            $existente = Proveedor::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nuevo)])->first();

            return $existente ?? Proveedor::create(['nombre' => $nuevo]);
        }

        $proveedor = Proveedor::find($request->input('proveedor_id'));
        abort_if(! $proveedor, 422, 'Selecciona o escribe un proveedor.');

        return $proveedor;
    }

    public function store(Request $request, ContadorService $contadores, StockService $stock, ComprobanteService $comprobantes)
    {
        $data = $request->validate($this->reglasItems());
        $proveedor = $this->resolverProveedor($request);

        $compra = DB::transaction(function () use ($data, $request, $proveedor, $contadores, $stock, $comprobantes) {
            $subtotal = 0;
            $detalles = [];
            foreach ($data['items'] as $it) {
                $producto = Producto::findOrFail($it['producto_id']);
                $cantidad = round((float) $it['cantidad'], 2);
                $costo = round((float) $it['costo'], 2);
                $sub = round($cantidad * $costo, 2);
                $subtotal = round($subtotal + $sub, 2);
                $detalles[] = compact('producto', 'cantidad', 'costo', 'sub');
            }

            $descuento = round((float) ($data['descuento'] ?? 0), 2);
            $total = max(0, round($subtotal - $descuento, 2));
            $numero = $contadores->siguienteUnico(
                $data['tipo'] === 'con_factura' ? 'FC-' : 'SF-',
                fn ($n) => Compra::withTrashed()->where('numero', $n)->exists()
            );

            $compra = Compra::create([
                'numero' => $numero,
                'tipo' => $data['tipo'],
                'modalidad' => $data['modalidad'] ?? 'contado',
                'proveedor_id' => $proveedor->id,
                'proveedor_nombre' => $proveedor->nombre,
                'fecha' => $data['fecha'],
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'base_cf' => $data['tipo'] === 'con_factura' ? $total : null,
                'credito_fiscal' => $data['tipo'] === 'con_factura' ? round($total * 0.13, 2) : null,
                'observaciones' => $request->input('observaciones'),
            ]);

            $snap = [];
            foreach ($detalles as $d) {
                $compra->detalles()->create([
                    'producto_id' => $d['producto']->id,
                    'codigo_producto' => $d['producto']->codigo,
                    'descripcion_producto' => $d['producto']->descripcion,
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['costo'],
                    'subtotal' => $d['sub'],
                ]);
                $d['producto']->update(['costo' => $d['costo']]);
                $stock->aumentarStock($d['producto']->fresh(), $d['cantidad']);
                $snap[] = [
                    'codigo_producto' => $d['producto']->codigo,
                    'descripcion_producto' => $d['producto']->descripcion,
                    'cantidad' => $d['cantidad'],
                ];
            }

            $comp = $comprobantes->crearParaCompra($compra->fresh(), $snap, $data['metodo'] ?? 'Efectivo');

            return ['compra' => $compra, 'comprobante' => $comp];
        });

        return redirect()->route('compras.index')
            ->with('exito', "Compra {$compra['compra']->numero} guardada. Comprobante {$compra['comprobante']->numero} generado.");
    }

    public function edit(Compra $compra)
    {
        $compra->load('detalles.producto');

        if ($compra->origen_siat) {
            return view('compras.edit-siat', compact('compra'));
        }

        return view('compras.edit', [
            'compra' => $compra,
            'proveedores' => Proveedor::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Compra $compra, StockService $stock, ComprobanteService $comprobantes)
    {
        // Compra SIAT: solo cabecera, sin tocar stock
        if ($compra->origen_siat) {
            $data = $request->validate([
                'proveedor_nombre' => 'required|string|max:255',
                'fecha' => 'required|date',
                'numero_factura_siat' => 'nullable|string|max:100',
                'total' => 'required|numeric|min:0',
                'base_cf' => 'nullable|numeric|min:0',
                'credito_fiscal' => 'nullable|numeric|min:0',
            ]);

            DB::transaction(function () use ($compra, $data) {
                $compra->update([
                    'proveedor_nombre' => $data['proveedor_nombre'],
                    'fecha' => $data['fecha'],
                    'numero_factura_siat' => $data['numero_factura_siat'] ?? null,
                    'total' => $data['total'],
                    'subtotal' => $data['total'],
                    'base_cf' => $data['base_cf'] ?? null,
                    'credito_fiscal' => $data['credito_fiscal'] ?? null,
                ]);
                $this->sincronizarComprobante($compra->fresh());
            });

            return redirect()->route('compras.index')->with('exito', 'Compra SIAT actualizada');
        }

        $data = $request->validate($this->reglasItems());
        $proveedor = $this->resolverProveedor($request);

        try {
            DB::transaction(function () use ($data, $request, $compra, $proveedor, $stock) {
                // Revertir stock anterior
                $compra->load('detalles.producto');
                foreach ($compra->detalles as $det) {
                    if ($det->producto) {
                        $stock->revertirStock($det->producto, (float) $det->cantidad, 'compra');
                    }
                }
                $compra->detalles()->delete();

                $subtotal = 0;
                $detalles = [];
                foreach ($data['items'] as $it) {
                    $producto = Producto::findOrFail($it['producto_id']);
                    $cantidad = round((float) $it['cantidad'], 2);
                    $costo = round((float) $it['costo'], 2);
                    $sub = round($cantidad * $costo, 2);
                    $subtotal = round($subtotal + $sub, 2);
                    $detalles[] = compact('producto', 'cantidad', 'costo', 'sub');
                }
                $descuento = round((float) ($data['descuento'] ?? 0), 2);
                $total = max(0, round($subtotal - $descuento, 2));

                $compra->update([
                    'tipo' => $data['tipo'],
                    'modalidad' => $data['modalidad'] ?? $compra->modalidad ?? 'contado',
                    'proveedor_id' => $proveedor->id,
                    'proveedor_nombre' => $proveedor->nombre,
                    'fecha' => $data['fecha'],
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'total' => $total,
                    'base_cf' => $data['tipo'] === 'con_factura' ? $total : null,
                    'credito_fiscal' => $data['tipo'] === 'con_factura' ? round($total * 0.13, 2) : null,
                    'observaciones' => $request->input('observaciones'),
                ]);

                foreach ($detalles as $d) {
                    $compra->detalles()->create([
                        'producto_id' => $d['producto']->id,
                        'codigo_producto' => $d['producto']->codigo,
                        'descripcion_producto' => $d['producto']->descripcion,
                        'cantidad' => $d['cantidad'],
                        'precio_unitario' => $d['costo'],
                        'subtotal' => $d['sub'],
                    ]);
                    $d['producto']->update(['costo' => $d['costo']]);
                    $stock->aumentarStock($d['producto']->fresh(), $d['cantidad']);
                }

                $this->sincronizarComprobante($compra->fresh());
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('compras.index')->with('exito', 'Compra actualizada');
    }

    protected function sincronizarComprobante(Compra $compra): void
    {
        $comp = Comprobante::where('origen_compra_id', $compra->id)->first();
        if (! $comp) {
            return;
        }
        $comp->update([
            'monto' => $compra->total,
            'entidad' => $compra->proveedor_nombre,
            'fecha' => $compra->fecha,
            'concepto' => $compra->origen_siat
                ? "Compra según Libro de Compras SIAT — Factura N° {$compra->numero_factura_siat}"
                : $comp->concepto,
        ]);
        // Mantener el pago principal sincronizado si hay un solo pago
        if ($comp->pagos()->count() === 1) {
            $comp->pagos()->update(['monto' => $compra->total]);
        }
    }

    public function destroy(Compra $compra, StockService $stock)
    {
        try {
            DB::transaction(function () use ($compra, $stock) {
                $compra->load('detalles.producto');
                if (! $compra->origen_siat) {
                    foreach ($compra->detalles as $det) {
                        if ($det->producto) {
                            $stock->revertirStock($det->producto, (float) $det->cantidad, 'compra');
                        }
                    }
                }
                Comprobante::where('origen_compra_id', $compra->id)->delete();
                $compra->delete();
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('compras.index')->with('exito', 'Compra eliminada');
    }

    // ---------- Importaciones ----------

    /**
     * Formato genérico CSV: Numero,Fecha,Proveedor,Tipo,Codigo,Descripcion,Cantidad,Costo,Descuento
     * Si detecta encabezados SIAT (CODIGO DE AUTORIZACION), aplica flujo SIAT.
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
        }

        return $this->respuestaImportacion($request, $res, 'compra(s)');
    }

    /**
     * Importación explícita del Libro de Compras SIAT (CSV del SIN).
     */
    public function importarSiat(Request $request, ContadorService $contadores, ComprobanteService $comprobantes)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:5120']);
        $texto = file_get_contents($request->file('archivo')->getRealPath());
        $filas = SiatCsvService::parsearCSV($texto);
        $res = $this->importarSiatFilas($filas, $contadores, $comprobantes);

        return $this->respuestaImportacion($request, $res, 'factura(s) del SIAT');
    }

    /**
     * Importación genérica desde Excel: el frontend (SheetJS) envía JSON { filas: [...] }
     * con las mismas columnas que el CSV genérico.
     */
    public function importarExcel(Request $request, ContadorService $contadores, StockService $stock, ComprobanteService $comprobantes)
    {
        $data = $request->validate(['filas' => 'required|array|min:1|max:2000']);
        $res = $this->importarGenericoFilas($data['filas'], $contadores, $stock, $comprobantes);

        return $this->respuestaImportacion($request, $res, 'compra(s)');
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
                if (Compra::where('codigo_autorizacion', $codigoAutorizacion)->exists()) {
                    $duplicadas++;

                    continue;
                }

                $nit = SiatCsvService::obtenerCampo($fila, 'NIT PROVEEDOR');
                $razon = SiatCsvService::obtenerCampo($fila, 'RAZON SOCIAL PROVEEDOR') ?: 'Proveedor SIAT s/n';
                $proveedor = $nit !== ''
                    ? Proveedor::firstOrCreate(['nit' => $nit], ['nombre' => $razon])
                    : Proveedor::firstOrCreate(['nombre' => $razon]);
                if ($proveedor->nombre !== $razon && $proveedor->wasRecentlyCreated === false) {
                    // mantener nombre existente
                }

                $total = (float) (SiatCsvService::obtenerCampo($fila, 'IMPORTE TOTAL COMPRA') ?: 0);
                $baseCF = (float) (SiatCsvService::obtenerCampo($fila, 'IMPORTE BASE CF') ?: 0);
                $credito = (float) (SiatCsvService::obtenerCampo($fila, 'CREDITO FISCAL') ?: 0);

                $compra = DB::transaction(function () use ($fila, $nit, $proveedor, $total, $baseCF, $credito, $codigoAutorizacion, $contadores, $comprobantes) {
                    $numero = $contadores->siguienteUnico('FC-', fn ($n) => Compra::withTrashed()->where('numero', $n)->exists());
                    $c = Compra::create([
                        'numero' => $numero,
                        'tipo' => 'con_factura',
                        'proveedor_id' => $proveedor->id,
                        'proveedor_nombre' => $proveedor->nombre,
                        'fecha' => SiatCsvService::fechaSiatAiso(SiatCsvService::obtenerCampo($fila, 'FECHA DE FACTURA/DUI/DIM')),
                        'subtotal' => $total,
                        'descuento' => 0,
                        'total' => $total,
                        'base_cf' => $baseCF,
                        'credito_fiscal' => $credito,
                        'origen_siat' => true,
                        'codigo_autorizacion' => $codigoAutorizacion,
                        'numero_factura_siat' => SiatCsvService::obtenerCampo($fila, 'NUMERO FACTURA'),
                        'nit_proveedor' => $nit,
                    ]);

                    $comp = $comprobantes->crearParaCompra($c, [], 'Registro SIAT');
                    $comp->update(['concepto' => "Compra según Libro de Compras SIAT — Factura N° {$c->numero_factura_siat}"]);

                    return $c;
                });

                if ($compra) {
                    $creadas++;
                }
            } catch (\Throwable $e) {
                $errores[] = 'Fila '.($i + 2).': '.$e->getMessage();
            }
        }

        return compact('creadas', 'duplicadas', 'errores');
    }

    protected function importarGenericoFilas(array $filas, ContadorService $contadores, StockService $stock, ComprobanteService $comprobantes): array
    {
        // Agrupar por Numero
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
                if ($numeroDoc && Compra::where('numero', $numeroDoc)->exists()) {
                    $duplicadas++;

                    continue;
                }

                $primera = $lineas[0];
                $tipoRaw = mb_strtoupper(trim((string) ($primera['Tipo'] ?? $primera['tipo'] ?? 'CON_FACTURA')));
                $tipo = str_contains($tipoRaw, 'SIN') ? 'sin_factura' : 'con_factura';
                $nombreProv = trim((string) ($primera['Proveedor'] ?? $primera['proveedor'] ?? 'Proveedor sin nombre')) ?: 'Proveedor sin nombre';
                $proveedor = Proveedor::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreProv)])->first()
                    ?? Proveedor::create(['nombre' => $nombreProv]);

                $items = [];
                foreach ($lineas as $l) {
                    $codigo = trim((string) ($l['Codigo'] ?? $l['codigo'] ?? ''));
                    if ($codigo === '') {
                        continue;
                    }
                    $producto = Producto::whereRaw('LOWER(codigo) = ?', [mb_strtolower($codigo)])->first();
                    if (! $producto) {
                        $costo = (float) ($l['Costo'] ?? $l['costo'] ?? 0);
                        $producto = Producto::create([
                            'codigo' => $codigo,
                            'descripcion' => trim((string) ($l['Descripcion'] ?? $l['descripcion'] ?? $codigo)),
                            'marca' => null,
                            'unidad' => 'PZA',
                            'costo' => $costo,
                            'precio' => round($costo * 1.3, 2),
                            'stock' => 0,
                            'stock_min' => 0,
                        ]);
                    }
                    $items[] = [
                        'producto' => $producto,
                        'cantidad' => (float) ($l['Cantidad'] ?? $l['cantidad'] ?? 0),
                        'costo' => (float) ($l['Costo'] ?? $l['costo'] ?? 0),
                    ];
                }

                if (empty($items)) {
                    throw new \RuntimeException("Documento {$numeroDoc}: sin items válidos.");
                }

                DB::transaction(function () use ($numeroDoc, $tipo, $primera, $proveedor, $items, $contadores, $stock, $comprobantes) {
                    $subtotal = round(array_sum(array_map(fn ($it) => $it['cantidad'] * $it['costo'], $items)), 2);
                    $descuento = round((float) ($primera['Descuento'] ?? $primera['descuento'] ?? 0), 2);
                    $total = max(0, round($subtotal - $descuento, 2));

                    $compra = Compra::create([
                        'numero' => $numeroDoc ?? $contadores->siguienteUnico($tipo === 'con_factura' ? 'FC-' : 'SF-', fn ($n) => Compra::withTrashed()->where('numero', $n)->exists()),
                        'tipo' => $tipo,
                        'proveedor_id' => $proveedor->id,
                        'proveedor_nombre' => $proveedor->nombre,
                        'fecha' => trim((string) ($primera['Fecha'] ?? $primera['fecha'] ?? '')) ?: date('Y-m-d'),
                        'subtotal' => $subtotal,
                        'descuento' => $descuento,
                        'total' => $total,
                        'base_cf' => $tipo === 'con_factura' ? $total : null,
                        'credito_fiscal' => $tipo === 'con_factura' ? round($total * 0.13, 2) : null,
                    ]);

                    $snap = [];
                    foreach ($items as $it) {
                        $sub = round($it['cantidad'] * $it['costo'], 2);
                        $compra->detalles()->create([
                            'producto_id' => $it['producto']->id,
                            'codigo_producto' => $it['producto']->codigo,
                            'descripcion_producto' => $it['producto']->descripcion,
                            'cantidad' => $it['cantidad'],
                            'precio_unitario' => $it['costo'],
                            'subtotal' => $sub,
                        ]);
                        $it['producto']->update(['costo' => $it['costo']]);
                        $stock->aumentarStock($it['producto']->fresh(), $it['cantidad']);
                        $snap[] = [
                            'codigo_producto' => $it['producto']->codigo,
                            'descripcion_producto' => $it['producto']->descripcion,
                            'cantidad' => $it['cantidad'],
                        ];
                    }

                    $comprobantes->crearParaCompra($compra->fresh(), $snap, 'Importado CSV');
                });

                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = 'Documento '.$numeroDoc.': '.$e->getMessage();
            }
        }

        return compact('creadas', 'duplicadas', 'errores');
    }
}
