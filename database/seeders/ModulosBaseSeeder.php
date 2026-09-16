<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ModulosBaseSeeder extends Seeder
{
    /**
     * Datos de ejemplo replicando DB.sembrarSiVacio() de js/data.js
     */
    public function run(): void
    {
        $productos = [
            ['codigo' => 'LF9009', 'equivalente' => 'P550949', 'descripcion' => 'Filtro de aceite Fleetguard para motor Cummins', 'marca' => 'Fleetguard', 'unidad' => 'PZA', 'costo' => 45.00, 'precio' => 89.90, 'stock' => 50, 'stock_min' => 10],
            ['codigo' => 'FF5320', 'equivalente' => '33966', 'descripcion' => 'Filtro de combustible Fleetguard', 'marca' => 'Fleetguard', 'unidad' => 'PZA', 'costo' => 38.00, 'precio' => 72.50, 'stock' => 18, 'stock_min' => 8],
            ['codigo' => 'VAL-15W40', 'equivalente' => '', 'descripcion' => 'Aceite Valvoline Premium Blue 15W-40, bidón 20L', 'marca' => 'Valvoline', 'unidad' => 'BID', 'costo' => 310.00, 'precio' => 459.90, 'stock' => 32, 'stock_min' => 6],
            ['codigo' => 'AF25550', 'equivalente' => 'C27883', 'descripcion' => 'Filtro de aire Fleetguard para Howo / Shacman', 'marca' => 'Fleetguard', 'unidad' => 'PZA', 'costo' => 62.00, 'precio' => 115.00, 'stock' => 4, 'stock_min' => 10],
        ];
        foreach ($productos as $p) {
            Producto::firstOrCreate(['codigo' => $p['codigo']], $p);
        }

        $clientes = [
            ['nombre' => 'Transportes Santa Cruz SRL', 'nit' => '1234567021', 'telefono' => '70011122', 'direccion' => 'Av. Cristo Redentor km 5', 'correo' => 'contacto@transportessc.com', 'contacto' => 'Juan Pérez'],
            ['nombre' => 'Flota Dongfeng Bolivia', 'nit' => '9876543012', 'telefono' => '76543210', 'direccion' => '', 'correo' => '', 'contacto' => ''],
            ['nombre' => 'Taller Los Andes', 'nit' => '1122334455', 'telefono' => '69874521', 'direccion' => '', 'correo' => '', 'contacto' => ''],
        ];
        foreach ($clientes as $c) {
            Cliente::firstOrCreate(['nombre' => $c['nombre']], $c);
        }

        $proveedores = [
            ['nombre' => 'Fleetguard Bolivia SRL', 'nit' => '9998887771', 'telefono' => '33221100', 'correo' => null, 'contacto' => null, 'direccion' => null],
            ['nombre' => 'Valvoline Import SRL', 'nit' => '5554443332', 'telefono' => '77889900', 'correo' => null, 'contacto' => null, 'direccion' => null],
        ];
        foreach ($proveedores as $p) {
            Proveedor::firstOrCreate(['nombre' => $p['nombre']], $p);
        }
    }
}
