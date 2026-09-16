<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\NitHelper;
use App\Services\SiatCsvService;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        $ordenables = ['nombre', 'nit'];
        $sort = in_array($request->get('sort'), $ordenables, true) ? $request->get('sort') : 'nombre';
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';

        $query = Cliente::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('nit', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $dir);

        $clientes = $query->paginate(50)->withQueryString();
        $seleccionado = null;
        if ($request->filled('ver')) {
            $seleccionado = Cliente::find($request->get('ver'));
        }

        return view('clientes.index', compact('clientes', 'q', 'seleccionado', 'sort', 'dir'));
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
        $cliente = Cliente::create($data);

        return redirect()->route('clientes.index', ['ver' => $cliente->id])
            ->with('exito', 'Cliente creado');
    }

    public function update(Request $request, Cliente $cliente)
    {
        $data = $request->validate($this->reglas());
        $cliente->update($data);

        return redirect()->route('clientes.index', ['ver' => $cliente->id, 'q' => $request->get('q')])
            ->with('exito', 'Cliente actualizado');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('clientes.index')->with('exito', 'Cliente eliminado');
    }

    /**
     * Importa clientes desde CSV (Nombre,NIT/CI,Telefono,Correo,Contacto,Direccion).
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
            $nit = trim((string) ($f['NIT'] ?? $f['nit'] ?? $f['NIT/CI'] ?? ''));
            $existe = Cliente::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->when($nit !== '', fn ($q) => $q->where('nit', $nit))
                ->exists();
            if ($existe) {
                $omitidos++;

                continue;
            }
            Cliente::create([
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
     * GET /clientes/buscar?q=... → [{id, nombre, nit}]
     */
    public function buscar(Request $request)
    {
        $q = trim($request->get('q', $request->get('termino', '')));

        $lista = Cliente::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('nit', 'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get(['id', 'nombre', 'nit']);

        return response()->json($lista);
    }
}
