<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Services\NitHelper;
use App\Services\SiatCsvService;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        $ordenables = ['nombre', 'nit'];
        $sort = in_array($request->get('sort'), $ordenables, true) ? $request->get('sort') : 'nombre';
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';

        $query = Proveedor::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('nit', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $dir);

        $proveedores = $query->paginate(50)->withQueryString();
        $seleccionado = null;
        if ($request->filled('ver')) {
            $seleccionado = Proveedor::find($request->get('ver'));
        }

        return view('proveedores.index', compact('proveedores', 'q', 'seleccionado', 'sort', 'dir'));
    }

    protected function reglas(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'nit' => ['nullable', 'string', 'max:50', function ($attr, $val, $fail) {
                if (! NitHelper::formatoValido($val)) {
                    $fail(NitHelper::mensajeFormato());
                }
            }],
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:150',
            'contacto' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:255',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->reglas());
        $proveedor = Proveedor::create($data);

        return redirect()->route('proveedores.index', ['ver' => $proveedor->id])
            ->with('exito', 'Proveedor creado');
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $data = $request->validate($this->reglas());
        $proveedor->update($data);

        return redirect()->route('proveedores.index', ['ver' => $proveedor->id, 'q' => $request->get('q')])
            ->with('exito', 'Proveedor actualizado');
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->delete();

        return redirect()->route('proveedores.index')->with('exito', 'Proveedor eliminado');
    }

    /**
     * Importa proveedores desde CSV (Nombre,NIT,Telefono,Correo,Contacto,Direccion).
     */
    public function importar(Request $request)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:5120']);
        $filas = SiatCsvService::parsearCSV(file_get_contents($request->file('archivo')->getRealPath()));

        $creados = 0;
        $omitidos = 0;
        foreach ($filas as $f) {
            $nombre = trim((string) ($f['Nombre'] ?? $f['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $nit = trim((string) ($f['NIT'] ?? $f['nit'] ?? ''));
            $existe = Proveedor::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->when($nit !== '', fn ($q) => $q->where('nit', $nit))
                ->exists();
            if ($existe) {
                $omitidos++;

                continue;
            }
            Proveedor::create([
                'nombre' => $nombre,
                'nit' => $nit ?: null,
                'telefono' => trim((string) ($f['Telefono'] ?? $f['telefono'] ?? '')) ?: null,
                'correo' => trim((string) ($f['Correo'] ?? $f['correo'] ?? '')) ?: null,
                'contacto' => trim((string) ($f['Contacto'] ?? $f['contacto'] ?? '')) ?: null,
                'direccion' => trim((string) ($f['Direccion'] ?? $f['direccion'] ?? '')) ?: null,
            ]);
            $creados++;
        }

        return back()->with('exito', "Importación: {$creados} creado(s), {$omitidos} duplicado(s).");
    }

    /**
     * GET /proveedores/buscar?q=... → [{id, nombre, nit}]
     */
    public function buscar(Request $request)
    {
        $q = trim($request->get('q', $request->get('termino', '')));

        $lista = Proveedor::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('nit', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get(['id', 'nombre', 'nit', 'telefono']);

        return response()->json($lista);
    }
}
