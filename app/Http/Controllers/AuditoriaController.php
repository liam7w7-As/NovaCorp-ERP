<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Auditoria::orderByDesc('id');

        if ($request->filled('modelo')) {
            $query->where('modelo', $request->get('modelo'));
        }
        if ($request->filled('accion')) {
            $query->where('accion', $request->get('accion'));
        }
        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('usuario_nombre', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhere('modelo_id', $q);
            });
        }

        $registros = $query->paginate(30)->withQueryString();
        $modelos = Auditoria::select('modelo')->distinct()->orderBy('modelo')->pluck('modelo');

        return view('auditoria.index', [
            'registros' => $registros,
            'modelos' => $modelos,
            'modelo' => $request->get('modelo', ''),
            'accion' => $request->get('accion', ''),
            'q' => $request->get('q', ''),
        ]);
    }
}
