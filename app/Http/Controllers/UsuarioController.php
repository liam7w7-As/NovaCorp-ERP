<?php

namespace App\Http\Controllers;

use App\Models\PermisoRol;
use App\Models\Rol;
use App\Models\User;
use App\Services\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::where('rol', '!=', Rol::OCULTO)->orderBy('name')->get();
        $roles = Rol::visibles()->orderBy('nombre')->get();

        // Matriz rol => [habilidad => bool]
        $matriz = [];
        foreach ($roles as $rol) {
            if (Permisos::esBloqueada($rol->clave)) {
                $matriz[$rol->clave] = array_fill_keys(Permisos::habilidades(), true);

                continue;
            }
            $mapa = Permisos::mapaDelRol($rol->clave);
            foreach (Permisos::habilidades() as $h) {
                $matriz[$rol->clave][$h] = (bool) ($mapa[$h] ?? false);
            }
        }

        // Habilidades agrupadas por módulo para la vista
        $grupos = [];
        foreach (Permisos::HABILIDADES as $h => [$modulo, $desc]) {
            $grupos[$modulo][$h] = $desc;
        }

        return view('usuarios.index', compact('usuarios', 'roles', 'matriz', 'grupos'));
    }

    // ---------------- Usuarios ----------------

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'rol' => 'required|exists:roles,clave',
        ]);

        abort_if($data['rol'] === Rol::OCULTO, 422, 'Rol no permitido.');

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'rol' => $data['rol'],
            'activo' => true,
        ]);

        return back()->with('exito', 'Usuario creado');
    }

    public function update(Request $request, User $usuario)
    {
        abort_if($usuario->rol === Rol::OCULTO, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$usuario->id,
            'password' => 'nullable|string|min:6',
            'rol' => 'required|exists:roles,clave',
            'activo' => 'nullable|boolean',
        ]);

        abort_if($data['rol'] === Rol::OCULTO, 422, 'Rol no permitido.');

        // Blindaje: no cambiar tu propio rol ni desactivarte
        if ($usuario->id === Auth::id()) {
            if ($data['rol'] !== $usuario->rol) {
                return back()->with('error', 'No puedes cambiar tu propio rol.');
            }
            $data['activo'] = true;
        }

        // Blindaje: no dejar el sistema sin administradores
        if ($usuario->rol === 'admin' && $data['rol'] !== 'admin') {
            $otros = User::where('rol', 'admin')->where('id', '!=', $usuario->id)->where('activo', true)->count();
            if ($otros === 0) {
                return back()->with('error', 'No puedes degradar al último administrador activo.');
            }
        }

        $usuario->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'rol' => $data['rol'],
            'activo' => (bool) ($data['activo'] ?? false),
        ] + ($request->filled('password') ? ['password' => $data['password']] : []));

        Permisos::olvidarCache($usuario->rol);

        return back()->with('exito', 'Usuario actualizado');
    }

    public function destroy(User $usuario)
    {
        abort_if($usuario->rol === Rol::OCULTO, 404);
        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }
        if ($usuario->rol === 'admin' && User::where('rol', 'admin')->where('activo', true)->count() <= 1) {
            return back()->with('error', 'No puedes eliminar al último administrador.');
        }

        $usuario->delete();

        return back()->with('exito', 'Usuario eliminado');
    }

    // ---------------- Roles ----------------

    public function storeRol(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $clave = Str::slug($data['nombre'], '_');
        abort_if(in_array($clave, [Rol::OCULTO, 'admin'], true) || Rol::where('clave', $clave)->exists(), 422, 'Nombre de rol no disponible.');

        Rol::create([
            'clave' => $clave,
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'es_sistema' => false,
        ]);

        return back()->with('exito', "Rol {$data['nombre']} creado. Activa sus permisos con los candados.");
    }

    public function destroyRol(Rol $rol)
    {
        abort_if($rol->es_sistema || Permisos::esBloqueada($rol->clave), 422, 'Rol del sistema, no eliminable.');
        if ($rol->usuarios()->count() > 0) {
            return back()->with('error', 'El rol tiene usuarios asignados. Reasígnalos primero.');
        }

        $rol->delete();
        Permisos::olvidarCache($rol->clave);

        return back()->with('exito', 'Rol eliminado');
    }

    // ---------------- Toggle inmediato ----------------

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'rol' => 'required|exists:roles,clave',
            'habilidad' => 'required|string',
        ]);

        abort_if(Permisos::esBloqueada($data['rol']), 422, 'Rol bloqueado.');
        abort_if(! array_key_exists($data['habilidad'], Permisos::HABILIDADES), 422, 'Habilidad inválida.');

        $rol = Rol::where('clave', $data['rol'])->firstOrFail();

        $permiso = PermisoRol::firstOrNew([
            'rol_id' => $rol->id,
            'habilidad' => $data['habilidad'],
        ]);
        $permiso->permitido = ! (bool) ($permiso->exists ? $permiso->permitido : false);
        $permiso->save();

        Permisos::olvidarCache($data['rol']);

        return response()->json([
            'rol' => $data['rol'],
            'habilidad' => $data['habilidad'],
            'permitido' => (bool) $permiso->permitido,
        ]);
    }
}
