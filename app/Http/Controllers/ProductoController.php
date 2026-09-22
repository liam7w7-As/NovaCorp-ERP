<?php

namespace App\Http\Controllers;

use App\Models\CatalogoSin;
use App\Models\Producto;
use App\Services\HomologacionProductoService;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $homologacionPendientes = Producto::where(function ($consulta): void {
            $consulta->whereNull('codigo_sin')->orWhere('codigo_sin', '');
        })->count();

        if ($request->expectsJson() || $request->get('format') === 'json') {
            return response()->json($productos);
        }

        return view('productos.index', compact('productos', 'q', 'sort', 'dir', 'homologacionPendientes'));
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
            'actividad_economica_sin' => 'nullable|string|max:20',
            'unidad_sin' => 'nullable|string|max:10',
            'costo' => 'nullable|numeric|min:0',
            'precio' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'stock_min' => 'nullable|numeric|min:0',
            'ficha_tecnica' => 'nullable|file|mimetypes:application/pdf|max:20480',
            'imagenes' => 'nullable|array|max:8',
            'imagenes.*' => 'file|mimetypes:image/jpeg,image/png,image/webp|max:8192',
            'quitar_ficha_tecnica' => 'nullable|boolean',
            'quitar_imagenes' => 'nullable|array',
            'quitar_imagenes.*' => 'integer|min:0',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->reglas());
        $atributos = Arr::except($data, [
            'ficha_tecnica',
            'imagenes',
            'quitar_ficha_tecnica',
            'quitar_imagenes',
        ]);

        $atributos = $this->completarHomologacion($atributos);
        $atributos['unidad'] = $atributos['unidad'] ?? 'PZA';
        $atributos['costo'] = $atributos['costo'] ?? 0;
        $atributos['precio'] = $atributos['precio'] ?? 0;
        $atributos['stock'] = $atributos['stock'] ?? 0;
        $atributos['stock_min'] = $atributos['stock_min'] ?? 0;

        // El código interno se genera con conteo: ante colisión concurrente
        // se regenera con conteo fresco (la columna es UNIQUE).
        $producto = null;
        for ($i = 0; ; $i++) {
            try {
                $producto = Producto::create($atributos);
                break;
            } catch (QueryException $e) {
                if ($i >= 3 || ! str_contains($e->getMessage(), 'codigo_interno')) {
                    throw $e;
                }
                $atributos['codigo_interno'] = null;
            }
        }

        $this->actualizarAdjuntosProducto($request, $producto);

        return back()->with('exito', 'Producto creado');
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $request->validate($this->reglas($producto));
        $atributos = Arr::except($data, [
            'ficha_tecnica',
            'imagenes',
            'quitar_ficha_tecnica',
            'quitar_imagenes',
        ]);

        $producto->update($this->completarHomologacion($atributos, $producto));
        $this->actualizarAdjuntosProducto($request, $producto->fresh());

        return back()->with('exito', 'Producto actualizado');
    }

    public function destroy(Producto $producto)
    {
        // En fases futuras se validará que no tenga movimientos asociados.
        $producto->delete();

        return back()->with('exito', 'Producto eliminado');
    }

    public function sugerenciasHomologacion(HomologacionProductoService $homologacion): JsonResponse
    {
        $catalogo = CatalogoSin::where('tipo', 'producto')->get();
        $productos = Producto::query()
            ->where(function ($consulta): void {
                $consulta->whereNull('codigo_sin')->orWhere('codigo_sin', '');
            })
            ->orderBy('descripcion')
            ->limit(200)
            ->get();

        $items = $productos->map(function (Producto $producto) use ($homologacion, $catalogo): array {
            return [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad_sin' => $producto->unidad_sin ?: '58',
                'sugerencias' => $homologacion->sugerencias($producto, $catalogo),
            ];
        });

        return response()->json([
            'items' => $items,
            'total_pendientes' => Producto::where(function ($consulta): void {
                $consulta->whereNull('codigo_sin')->orWhere('codigo_sin', '');
            })->count(),
            'catalogo_disponible' => $catalogo->isNotEmpty(),
        ]);
    }

    public function homologar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'homologaciones' => 'required|array|min:1|max:200',
            'homologaciones.*.producto_id' => 'required|integer|distinct|exists:productos,id',
            'homologaciones.*.codigo_sin' => [
                'required',
                'string',
                Rule::exists('catalogos_sin', 'codigo')->where('tipo', 'producto'),
            ],
            'homologaciones.*.unidad_sin' => 'nullable|string|max:10',
            'homologaciones.*.confianza' => 'nullable|integer|min:0|max:100',
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['homologaciones'] as $homologacion) {
                $producto = Producto::whereKey($homologacion['producto_id'])->lockForUpdate()->firstOrFail();
                $catalogo = CatalogoSin::where('tipo', 'producto')
                    ->where('codigo', $homologacion['codigo_sin'])
                    ->firstOrFail();
                $extra = is_array($catalogo->extra) ? $catalogo->extra : [];

                $producto->update([
                    'codigo_sin' => (string) $catalogo->codigo,
                    'actividad_economica_sin' => filled($extra['actividad_economica'] ?? null)
                        ? (string) $extra['actividad_economica']
                        : null,
                    'unidad_sin' => $homologacion['unidad_sin'] ?: ($producto->unidad_sin ?: '58'),
                    'homologacion_estado' => 'confirmada',
                    'homologacion_confianza' => $homologacion['confianza'] ?? null,
                    'homologado_at' => now(),
                    'homologado_por' => Auth::id(),
                ]);
            }
        });

        $cantidad = count($data['homologaciones']);

        return response()->json([
            'message' => "{$cantidad} producto(s) homologado(s) correctamente.",
            'actualizados' => $cantidad,
        ]);
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
            ->get(['id', 'codigo_interno', 'codigo', 'equivalente', 'descripcion', 'marca', 'unidad', 'costo', 'precio', 'stock', 'stock_reservado', 'stock_min']);

        return response()->json($lista);
    }

    /**
     * Importación masiva desde Excel (SheetJS en frontend).
     * POST /productos/importar — recibe { productos: [ {Codigo, Equivalente, ...} ] }
     * Replica DB.importarProductosExcel: si el código existe actualiza y SUMA stock; si no, crea.
     */
    public function importar(Request $request, StockService $stock)
    {
        $request->validate([
            'productos' => 'sometimes|array|max:1000',
            'filas' => 'sometimes|array|max:1000',
        ]);
        $filas = $request->input('productos', $request->input('filas', []));

        if (! is_array($filas) || empty($filas)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sin filas para importar'], 422);
            }

            return back()->with('error', 'Selecciona un archivo Excel con datos');
        }

        $creados = 0;
        $actualizados = 0;
        $restaurados = 0;
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
                // Excel arrastra espacios invisibles (nbsp, etc.) que trim() no
                // quita y rompen la coincidencia con lo ya registrado.
                $codigo = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $codigo) ?? '';
                if ($codigo === '') {
                    continue; // fila vacía, se ignora
                }
                $descripcion = trim((string) $obtener($fila, ['Descripcion', 'Descripción'])) ?: $codigo;
                $equivalente = trim((string) $obtener($fila, ['Equivalente']));
                $marca = trim((string) $obtener($fila, ['Marca']));
                $unidad = trim((string) $obtener($fila, ['Unidad'])) ?: 'PZA';
                $costoRaw = $obtener($fila, ['Costo']);
                $precioRaw = $obtener($fila, ['PrecioVenta', 'Precio Venta', 'Precio']);
                $stockRaw = $obtener($fila, ['Stock']);
                $stockMinRaw = $obtener($fila, ['StockMinimo', 'Stock Minimo', 'Stock Mínimo']);
                $costo = (float) ($costoRaw === '' ? 0 : $costoRaw);
                $precio = (float) ($precioRaw === '' ? 0 : $precioRaw);
                $stockExcel = (float) ($stockRaw === '' ? 0 : $stockRaw);
                $stockMin = (float) ($stockMinRaw === '' ? 0 : $stockMinRaw);

                // Buscar incluyendo papelera: un código borrado debe
                // restaurarse, no intentar INSERT (error 1062).
                $existente = Producto::withTrashed()
                    ->whereRaw('LOWER(TRIM(codigo)) = ?', [mb_strtolower($codigo)])
                    ->first();
                if (! $existente) {
                    // Respaldo exacto para variantes invisibles restantes.
                    $existente = Producto::withTrashed()->where('codigo', $codigo)->first();
                }

                $datosFila = compact(
                    'descripcion', 'equivalente', 'marca', 'unidad',
                    'costoRaw', 'precioRaw', 'stockMinRaw',
                    'costo', 'precio', 'stockMin', 'stockExcel'
                );

                if ($existente) {
                    $restaurado = false;
                    if ($existente->trashed()) {
                        $existente->restore();
                        $restaurado = true;
                    }
                    $this->aplicarActualizacionImportada($existente, $datosFila, $stock);
                    $restaurado ? $restaurados++ : $actualizados++;
                } else {
                    try {
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
                    } catch (QueryException $e) {
                        if (! $this->esErrorDuplicado($e)) {
                            throw $e;
                        }
                        // El código apareció entre la búsqueda y el INSERT
                        // (o hay una variante no detectada): recuperar y actualizar.
                        $existente = Producto::withTrashed()->where('codigo', $codigo)->first()
                            ?? Producto::withTrashed()
                                ->whereRaw('LOWER(TRIM(codigo)) = ?', [mb_strtolower($codigo)])
                                ->firstOrFail();
                        $restaurado = false;
                        if ($existente->trashed()) {
                            $existente->restore();
                            $restaurado = true;
                        }
                        $this->aplicarActualizacionImportada($existente, $datosFila, $stock);
                        $restaurado ? $restaurados++ : $actualizados++;
                    }
                }
            } catch (\Throwable $e) {
                $errores[] = 'Fila '.($i + 2).': '.$e->getMessage();
            }
        }

        $mensaje = "Importación completa: {$creados} nuevo(s), {$actualizados} actualizado(s), {$restaurados} restaurado(s)";
        if (! empty($errores)) {
            $mensaje .= ', '.count($errores).' fila(s) con error';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'creados' => $creados,
                'actualizados' => $actualizados,
                'restaurados' => $restaurados,
                'errores' => $errores,
                'message' => $mensaje,
            ]);
        }

        return back()->with('exito', $mensaje);
    }

    /**
     * Aplica los datos de una fila Excel sobre un producto existente
     * (con lock): actualiza ficha sin pisar con vacíos y SUMA stock.
     *
     * @param  array{descripcion:string,equivalente:string,marca:string,unidad:string,costoRaw:mixed,precioRaw:mixed,stockMinRaw:mixed,costo:float,precio:float,stockMin:float,stockExcel:float}  $p
     */
    protected function aplicarActualizacionImportada(Producto $existente, array $p, StockService $stock): void
    {
        DB::transaction(function () use ($existente, $p, $stock) {
            $bloqueado = Producto::withTrashed()->whereKey($existente->id)->lockForUpdate()->firstOrFail();
            // No pisar costo/precio/stock_min con 0 cuando la celda viene vacía.
            $bloqueado->update([
                'descripcion' => $p['descripcion'],
                'equivalente' => $p['equivalente'] ?: $bloqueado->equivalente,
                'marca' => $p['marca'] ?: $bloqueado->marca,
                'unidad' => $p['unidad'],
                'costo' => $p['costoRaw'] === '' ? $bloqueado->costo : $p['costo'],
                'precio' => $p['precioRaw'] === '' ? $bloqueado->precio : $p['precio'],
                'stock_min' => $p['stockMinRaw'] === '' ? $bloqueado->stock_min : $p['stockMin'],
            ]);
            if ($p['stockExcel'] > 0) {
                $stock->aumentarStock($bloqueado->fresh(), $p['stockExcel']);
            }
        });
    }

    protected function esErrorDuplicado(QueryException $e): bool
    {
        $mensaje = $e->getMessage();

        return str_contains($mensaje, 'Duplicate entry') || str_contains($mensaje, 'UNIQUE constraint');
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function completarHomologacion(array $atributos, ?Producto $producto = null): array
    {
        $codigo = trim((string) ($atributos['codigo_sin'] ?? ''));

        if ($codigo === '') {
            $atributos['codigo_sin'] = null;
            $atributos['actividad_economica_sin'] = null;
            $atributos['homologacion_estado'] = 'pendiente';
            $atributos['homologacion_confianza'] = null;
            $atributos['homologado_at'] = null;
            $atributos['homologado_por'] = null;

            return $atributos;
        }

        $catalogo = CatalogoSin::where('tipo', 'producto')->where('codigo', $codigo)->first();
        $extra = is_array($catalogo?->extra) ? $catalogo->extra : [];
        $atributos['codigo_sin'] = $codigo;
        $atributos['actividad_economica_sin'] = trim((string) ($atributos['actividad_economica_sin'] ?? ''))
            ?: ($extra['actividad_economica'] ?? null);
        $atributos['homologacion_estado'] = 'confirmada';

        if (! $producto || $producto->codigo_sin !== $codigo || $producto->homologacion_estado !== 'confirmada') {
            $atributos['homologacion_confianza'] = 100;
            $atributos['homologado_at'] = now();
            $atributos['homologado_por'] = Auth::id();
        }

        return $atributos;
    }

    private function actualizarAdjuntosProducto(Request $request, Producto $producto): void
    {
        $atributos = [];

        if ($request->boolean('quitar_ficha_tecnica') || $request->hasFile('ficha_tecnica')) {
            $this->eliminarArchivoPublico($producto->ficha_tecnica_path);
            $atributos['ficha_tecnica_path'] = null;
            $atributos['ficha_tecnica_nombre'] = null;
            $atributos['ficha_tecnica_mime'] = null;
            $atributos['ficha_tecnica_tamano'] = null;
        }

        if ($request->hasFile('ficha_tecnica')) {
            $ficha = $this->guardarArchivoProducto($producto, $request->file('ficha_tecnica'), 'fichas');
            $atributos['ficha_tecnica_path'] = $ficha['path'];
            $atributos['ficha_tecnica_nombre'] = $ficha['nombre'];
            $atributos['ficha_tecnica_mime'] = $ficha['mime'];
            $atributos['ficha_tecnica_tamano'] = $ficha['tamano'];
        }

        $imagenes = collect($producto->imagenes ?? [])->values();
        $imagenesCambiaron = false;
        $indicesQuitar = collect($request->input('quitar_imagenes', []))
            ->map(fn (mixed $indice): int => (int) $indice)
            ->unique()
            ->values()
            ->all();

        if ($indicesQuitar !== []) {
            foreach ($imagenes as $indice => $imagen) {
                if (in_array((int) $indice, $indicesQuitar, true)) {
                    $this->eliminarArchivoPublico($imagen['path'] ?? null);
                }
            }

            $imagenes = $imagenes
                ->reject(fn (array $imagen, int $indice): bool => in_array($indice, $indicesQuitar, true))
                ->values();
            $imagenesCambiaron = true;
        }

        foreach ($request->file('imagenes', []) as $imagen) {
            if ($imagen instanceof UploadedFile) {
                $imagenes->push($this->guardarArchivoProducto($producto, $imagen, 'imagenes'));
                $imagenesCambiaron = true;
            }
        }

        if ($imagenesCambiaron) {
            $atributos['imagenes'] = $imagenes->isEmpty() ? null : $imagenes->values()->all();
        }

        if ($atributos !== []) {
            $producto->update($atributos);
        }
    }

    /** @return array{path: string, nombre: string, mime: string|null, tamano: int|null} */
    private function guardarArchivoProducto(Producto $producto, UploadedFile $archivo, string $carpeta): array
    {
        $path = $archivo->storeAs(
            "productos/{$producto->id}/{$carpeta}",
            $this->nombreArchivoSeguro($archivo),
            'public'
        );

        return [
            'path' => $path,
            'nombre' => $archivo->getClientOriginalName(),
            'mime' => $archivo->getMimeType() ?: $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize() ?: null,
        ];
    }

    private function nombreArchivoSeguro(UploadedFile $archivo): string
    {
        $base = Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'archivo';
        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension() ?: 'bin');

        return now()->format('YmdHis').'-'.Str::random(8).'-'.$base.'.'.$extension;
    }

    private function eliminarArchivoPublico(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
