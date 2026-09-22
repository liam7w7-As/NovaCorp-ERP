<?php

namespace App\Http\Controllers;

use App\Models\CatalogoSin;
use App\Services\SiatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoController extends Controller
{
    public function index()
    {
        $grupos = [];
        $totalGeneral = 0;
        foreach (CatalogoSin::TIPOS as $tipo => $etiqueta) {
            $items = CatalogoSin::lista($tipo);
            $grupos[$tipo] = [
                'etiqueta' => $etiqueta,
                'items' => $items,
                'count' => $items->count(),
            ];
            $totalGeneral += $items->count();
        }

        return view('catalogos.index', compact('grupos', 'totalGeneral'));
    }

    public function sincronizar(Request $request, SiatService $siat)
    {
        $tiposValidos = array_keys(CatalogoSin::TIPOS);
        $data = $request->validate([
            'tipo' => 'nullable|in:'.implode(',', $tiposValidos),
        ]);

        $tipos = ! empty($data['tipo']) ? [$data['tipo']] : $tiposValidos;
        $total = 0;

        try {
            foreach ($tipos as $tipo) {
                $res = $siat->sincronizarCatalogo($tipo);
                $items = $res['items'] ?? [];
                $extras = $res['extras'] ?? [];
                DB::transaction(function () use ($tipo, $items, $extras, &$total): void {
                    if ($items !== []) {
                        CatalogoSin::where('tipo', $tipo)
                            ->whereNotIn('codigo', array_map('strval', array_keys($items)))
                            ->delete();
                    }

                    foreach ($items as $codigo => $descripcion) {
                        $atributos = ['descripcion' => (string) $descripcion];
                        if (isset($extras[$codigo]) && $extras[$codigo] !== []) {
                            $atributos['extra'] = $extras[$codigo];
                        }

                        CatalogoSin::updateOrCreate(
                            ['tipo' => $tipo, 'codigo' => (string) $codigo],
                            $atributos
                        );
                        $total++;
                    }
                });
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Falló la sincronización: '.$e->getMessage());
        }

        return back()->with('exito', "Catálogos SIN sincronizados ({$total} registros actualizados)");
    }

    /**
     * Búsqueda de productos SIN para homologación. GET /catalogos/productos?q=
     */
    public function buscarProducto(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $items = CatalogoSin::where('tipo', 'producto')
            ->where(function ($s) use ($q) {
                $s->where('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get(['codigo', 'descripcion', 'extra'])
            ->map(function (CatalogoSin $item): array {
                $extra = is_array($item->extra) ? $item->extra : [];

                return [
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'actividad_economica' => $extra['actividad_economica'] ?? null,
                ];
            });

        return response()->json($items);
    }

    /**
     * Búsqueda de unidades de medida SIN. GET /catalogos/unidades?q=
     */
    public function buscarUnidad(Request $request)
    {
        $q = trim($request->get('q', ''));
        $query = CatalogoSin::where('tipo', 'unidad');

        if ($q !== '') {
            $query->where(function ($s) use ($q) {
                $s->where('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->orderBy('codigo')->limit(30)->get(['codigo', 'descripcion'])
        );
    }
}
