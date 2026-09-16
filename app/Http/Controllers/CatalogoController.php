<?php

namespace App\Http\Controllers;

use App\Models\CatalogoSin;
use App\Services\SiatService;
use Illuminate\Http\Request;

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
                foreach ($res['items'] as $codigo => $descripcion) {
                    CatalogoSin::updateOrCreate(
                        ['tipo' => $tipo, 'codigo' => (string) $codigo],
                        ['descripcion' => (string) $descripcion]
                    );
                    $total++;
                }
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
            ->get(['codigo', 'descripcion']);

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
