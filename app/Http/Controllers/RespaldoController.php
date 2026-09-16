<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RespaldoController extends Controller
{
    public function index()
    {
        $archivos = collect(Storage::files('respaldos'))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->sortDesc()
            ->values()
            ->map(fn ($f) => [
                'nombre' => basename($f),
                'kb' => round(Storage::size($f) / 1024, 1),
                'fecha' => date('Y-m-d H:i:s', Storage::lastModified($f)),
            ]);

        return view('respaldos.index', compact('archivos'));
    }

    public function crear()
    {
        $codigo = Artisan::call('backup:database');
        $salida = trim(Artisan::output());

        if ($codigo === 0) {
            Auditoria::create([
                'usuario_id' => Auth::id(),
                'usuario_nombre' => Auth::user()->name ?? 'sistema',
                'accion' => 'respaldo',
                'modelo' => 'Respaldo',
                'descripcion' => $salida,
                'ip' => request()->ip(),
            ]);
        }

        return back()->with(
            $codigo === 0 ? 'exito' : 'error',
            $codigo === 0 ? $salida : 'Falló el respaldo. '.$salida
        );
    }

    public function descargar(string $archivo)
    {
        $nombre = basename($archivo);
        if (! str_ends_with($nombre, '.sql') || ! Storage::exists('respaldos/'.$nombre)) {
            abort(404);
        }

        Auditoria::create([
            'usuario_id' => Auth::id(),
            'usuario_nombre' => Auth::user()->name ?? 'sistema',
            'accion' => 'descarga',
            'modelo' => 'Respaldo',
            'descripcion' => $nombre,
            'ip' => request()->ip(),
        ]);

        return Storage::download('respaldos/'.$nombre);
    }
}
