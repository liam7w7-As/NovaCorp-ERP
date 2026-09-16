<?php

namespace Database\Seeders;

use App\Models\CatalogoSin;
use App\Services\SiatService;
use Illuminate\Database\Seeder;

class CatalogoSinSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = array_keys(CatalogoSin::TIPOS);

        foreach ($tipos as $tipo) {
            $items = SiatService::catalogoEjemplo($tipo);
            foreach ($items as $codigo => $descripcion) {
                CatalogoSin::updateOrCreate(
                    ['tipo' => $tipo, 'codigo' => (string) $codigo],
                    ['descripcion' => (string) $descripcion]
                );
            }
        }
    }
}
