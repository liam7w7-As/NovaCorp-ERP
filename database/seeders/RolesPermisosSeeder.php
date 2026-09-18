<?php

namespace Database\Seeders;

use App\Models\PermisoRol;
use App\Models\Rol;
use App\Models\User;
use App\Services\Permisos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['clave' => Rol::OCULTO, 'nombre' => 'Superadmin', 'descripcion' => 'Acceso total, oculto en la interfaz', 'es_sistema' => true],
            ['clave' => 'admin', 'nombre' => 'Administrador', 'descripcion' => 'Acceso total', 'es_sistema' => true],
            ['clave' => 'vendedor', 'nombre' => 'Vendedor', 'descripcion' => 'Ventas, proformas y clientes', 'es_sistema' => false],
            ['clave' => 'contador', 'nombre' => 'Contador', 'descripcion' => 'Compras, comprobantes y tributario', 'es_sistema' => false],
            ['clave' => 'almacen', 'nombre' => 'Almacén', 'descripcion' => 'Preparación y entrega de pedidos', 'es_sistema' => false],
            ['clave' => 'gerencia', 'nombre' => 'Gerencia', 'descripcion' => 'Supervisión comercial y del CRM', 'es_sistema' => false],
        ];
        foreach ($roles as $r) {
            Rol::updateOrCreate(['clave' => $r['clave']], $r);
        }

        // Matriz inicial para roles no bloqueados
        foreach (Permisos::MATRIZ as $habilidad => $claves) {
            foreach (Rol::whereNotIn('clave', [Rol::OCULTO, 'admin'])->get() as $rol) {
                PermisoRol::updateOrCreate(
                    ['rol_id' => $rol->id, 'habilidad' => $habilidad],
                    ['permitido' => in_array($rol->clave, $claves, true)]
                );
            }
        }

        Permisos::olvidarCache();

        if (! User::where('email', 'superadmin@giseca.com')->exists()) {
            $clave = Str::random(20);
            User::create(
                [
                    'name' => 'Super Administrador',
                    'email' => 'superadmin@giseca.com',
                    'password' => Hash::make($clave),
                    'rol' => Rol::OCULTO,
                    'activo' => true,
                ]
            );
            $this->command?->warn("Superadmin creado con clave inicial: {$clave} (guárdala, no se vuelve a mostrar).");
        }
    }
}
