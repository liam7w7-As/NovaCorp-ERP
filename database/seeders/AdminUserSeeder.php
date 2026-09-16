<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea usuarios demo SOLO si no existen. Nunca resetea contraseñas
     * existentes: en instalaciones frescas genera claves aleatorias
     * que se muestran una vez por consola.
     */
    public function run(): void
    {
        $usuarios = [
            ['name' => 'Administrador', 'email' => 'admin@giseca.com', 'rol' => 'admin'],
            ['name' => 'Vendedor Demo', 'email' => 'vendedor@giseca.com', 'rol' => 'vendedor'],
            ['name' => 'Contador Demo', 'email' => 'contador@giseca.com', 'rol' => 'contador'],
        ];

        foreach ($usuarios as $u) {
            if (User::where('email', $u['email'])->exists()) {
                continue;
            }
            $clave = Str::random(16);
            User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make($clave),
                'rol' => $u['rol'],
                'activo' => true,
            ]);
            $this->command?->warn("Usuario {$u['email']} creado con clave inicial: {$clave} (guárdala, no se vuelve a mostrar).");
        }
    }
}
