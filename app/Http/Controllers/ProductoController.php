<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorHTML;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        $ordenables = ['codigo', 'descripcion', 'marca', 'costo', 'precio', 'stock'];
        $sort = in_array($request->get('sort'), $ordenables, true) ? $request->get('sort') : 'descripcion';
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';

        $query = Producto::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo_interno', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                        ->orWhere('descripcion', 'like', "%{$q}%")
                        ->orWhere('marca', 'like', "%{$q}%")
                        ->orWhere('equivalente', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $dir);

        $productos = $query->paginate(50)->withQueryString();

        if ($request->expectsJson() || $request->get('format') === 'json') {
            return response()->json($productos);
        }

        return view('productos.index', compact('productos', 'q', 'sort', 'dir'));
    }

    /**
     * Devuelve el código de barras Code-128 del producto (HTML imprimible).
     * GET /productos/{producto}/codigo-barra
     */
    public function codigoBarra(Producto $producto)
    {
        $gen = new BarcodeGeneratorHTML;

        return response()->json([
            'codigo' => $producto->codigo,
            'descripcion' => $producto->descripcion,
            'html' => $gen->getBarcode($producto->codigo, $gen::TYPE_CODE_128, 2, 60),
        ]);
    }

    protected function reglas(?Producto $producto = null): array
    {
        $ignore = $producto ? ','.$producto->id : '';

        return [
            'codigo_interno' => 'nullable|string|max:50|unique:productos,codigo_interno'.$ignore,
            'codigo' => 'required|string|max:100|unique:productos,codigo'.$ignore,
            'equivalente' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:100',
            'descripcion' => 'required|string|max:255',
            'unidad' => 'nullable|string|max:20',
            'codigo_sin' => 'nullable|string|max:20',
            'unidad_sin' => 'nullable|string|max:10',
            'costo' => 'nullable|numeric|min:0',
            'precio' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric',
            'stock_min' => 'nullable|numeric|min:0',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->reglas());

        $data['unidad'] = $data['unidad'] ?? 'PZA';
        $data['costo'] = $data['costo'] ?? 0;
        $data['precio'] = $data['precio'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;
        $data['stock_min'] = $data['stock_min'] ?? 0;

        Producto::create($data);

        return back()->with('exito', 'Producto creado');
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $request->validate($this->reglas($producto));

        $producto->update($data);

        return back()->with('exito', 'Producto actualizado');
    }

    public function destroy(Producto $producto)
    {
        // En fases futuras se validará que no tenga movimientos asociados.
        $producto->delete();

        return back()->with('exito', 'Producto eliminado');
    }

    /**
     * Búsqueda en vivo / autocompletado para proformas, compras y ventas futuras.
     * GET /productos/buscar?q=...
     */
    public function buscar(Request $request)
    {
        $q = trim($request->get('q', $request->get('termino', '')));

        $lista = Producto::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo_interno', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                        ->orWhere('descripcion', 'like', "%{$q}%")
                        ->orWhere('equivalente', 'like', "%{$q}%")
                        ->orWhere('marca', 'like', "%{$q}%");
                });
            })
            ->orderBy('descripcion')
            ->limit(15)
            ->get(['id', 'codigo_interno', 'codigo', 'equivalente', 'descripcion', 'marca', 'unidad', 'costo', 'precio', 'stock', 'stock_min']);

        return response()->json($lista);
    }

    /**
     * Importación masiva desde Excel (SheetJS en frontend).
     * POST /productos/importar — recibe { productos: [ {Codigo, Equivalente, ...} ] }
     * Replica DB.importarProductosExcel: si el código existe actualiza y SUMA stock; si no, crea.
     */
    public function importar(Request $request)
    {
        $filas = $request->input('productos', $request->input('filas', []));

        if (! is_array($filas) || empty($filas)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sin filas para importar'], 422);
            }

            return back()->with('error', 'Selecciona un archivo Excel con datos');
        }

        $creados = 0;
        $actualizados = 0;
        $errores = [];

        $obtener = function (array $fila, array $claves) {
            // Acepta encabezados con distinta capitalización / tildes / espacios
            $normalizar = fn ($s) => trim(mb_strtolower($s ?? ''));
            $mapa = [];
            foreach ($fila as $k => $v) {
                $mapa[$normalizar($k)] = $v;
            }
            foreach ($claves as $c) {
                $nc = $normalizar($c);
                if (array_key_exists($nc, $mapa) && $mapa[$nc] !== '' && $mapa[$nc] !== null) {
                    return $mapa[$nc];
                }
            }
            // Alias sin tilde
            $sinTilde = fn ($s) => strtr(mb_strtolower(trim($s)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
            $mapa2 = [];
            foreach ($fila as $k => $v) {
                $mapa2[$sinTilde($k)] = $v;
            }
            foreach ($claves as $c) {
                $nc = $sinTilde($c);
                if (array_key_exists($nc, $mapa2) && $mapa2[$nc] !== '' && $mapa2[$nc] !== null) {
                    return $mapa2[$nc];
                }
            }

            return '';
        };

        foreach ($filas as $i => $fila) {
            try {
                if (! is_array($fila)) {
                    continue;
                }
                $codigo = trim((string) $obtener($fila, ['Codigo', 'Código']));
                if ($codigo === '') {
                    continue; // fila vacía, se ignora
                }
                $descripcion = trim((string) $obtener($fila, ['Descripcion', 'Descripción'])) ?: $codigo;
                $equivalente = trim((string) $obtener($fila, ['Equivalente']));
                $marca = trim((string) $obtener($fila, ['Marca']));
                $unidad = trim((string) $obtener($fila, ['Unidad'])) ?: 'PZA';
                $costo = (float) ($obtener($fila, ['Costo']) ?: 0);
                $precio = (float) ($obtener($fila, ['PrecioVenta', 'Precio Venta', 'Precio']) ?: 0);
                $stockExcel = (float) ($obtener($fila, ['Stock']) ?: 0);
                $stockMin = (float) ($obtener($fila, ['StockMinimo', 'Stock Minimo', 'Stock Mínimo']) ?: 0);

                $existente = Producto::whereRaw('LOWER(codigo) = ?', [mb_strtolower($codigo)])->first();

                if ($existente) {
                    $existente->update([
                        'descripcion' => $descripcion,
                        'equivalente' => $equivalente ?: $existente->equivalente,
                        'marca' => $marca ?: $existente->marca,
                        'unidad' => $unidad,
                        'costo' => $costo,
                        'precio' => $precio ?: $existente->precio,
                        'stock_min' => $stockMin ?: $existente->stock_min,
                        'stock' => round(((float) $existente->stock + ($stockExcel > 0 ? $stockExcel : 0)) * 100) / 100,
                    ]);
                    $actualizados++;
                } else {
                    Producto::create([
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'equivalente' => $equivalente ?: null,
                        'marca' => $marca ?: null,
                        'unidad' => $unidad,
                        'costo' => $costo,
                        'precio' => $precio,
                        'stock' => $stockExcel,
                        'stock_min' => $stockMin,
                    ]);
                    $creados++;
                }
            } catch (\Throwable $e) {
                $errores[] = 'Fila '.($i + 2).': '.$e->getMessage();
            }
        }

        $mensaje = "Importación completa: {$creados} nuevo(s), {$actualizados} actualizado(s)";

        if ($request->expectsJson()) {
            return response()->json([
                'creados' => $creados,
                'actualizados' => $actualizados,
                'errores' => $errores,
                'message' => $mensaje,
            ]);
        }

        return back()->with('exito', $mensaje);
    }
}
