<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usuarios = [
            ['name' => 'Administrador', 'email' => 'admin@giseca.com', 'password' => 'admin123', 'rol' => 'admin'],
            ['name' => 'Vendedor Demo', 'email' => 'vendedor@giseca.com', 'password' => 'vendedor123', 'rol' => 'vendedor'],
            ['name' => 'Contador Demo', 'email' => 'contador@giseca.com', 'password' => 'contador123', 'rol' => 'contador'],
        ];

        foreach ($usuarios as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'password' => Hash::make($u['password']),
                    'rol' => $u['rol'],
                ]
            );
        }
    }
}
