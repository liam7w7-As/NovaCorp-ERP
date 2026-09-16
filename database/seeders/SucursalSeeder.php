<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\EventoContingencia;
use App\Models\FacturaElectronica;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    public function run(): void
    {
        $casaMatriz = Sucursal::firstOrCreate(
            ['codigo' => 0],
            [
                'nombre' => 'Casa Matriz',
                'direccion' => Configuracion::get('empresa_direccion', 'Av. Cristo Redentor #100'),
                'telefono' => Configuracion::get('empresa_telefono', '3-3456789'),
                'municipio' => Configuracion::get('siat_ciudad', 'Santa Cruz de la Sierra'),
                'activa' => true,
            ]
        );

        $puntoPrincipal = PuntoVenta::firstOrCreate(
            ['sucursal_id' => $casaMatriz->id, 'codigo' => 0],
            [
                'nombre' => 'Caja Central (POS 0)',
                'tipo_punto_venta' => 'Punto de Venta Fijo',
                'cuis' => Configuracion::get('siat_cuis'),
                'cuis_vigencia' => now()->addYear(),
                'cufd' => Configuracion::get('siat_cufd'),
                'codigo_control' => Configuracion::get('siat_cufd_control'),
                'cufd_vigencia' => now()->addDay(),
                'activo' => true,
            ]
        );

        // Vincular usuarios existentes sin sucursal
        User::whereNull('sucursal_id')->update([
            'sucursal_id' => $casaMatriz->id,
            'punto_venta_id' => $puntoPrincipal->id,
        ]);

        // Vincular ventas existentes
        Venta::whereNull('sucursal_id')->update([
            'sucursal_id' => $casaMatriz->id,
            'punto_venta_id' => $puntoPrincipal->id,
            'codigo_sucursal' => 0,
            'codigo_punto_venta' => 0,
        ]);

        // Vincular facturas existentes
        FacturaElectronica::whereNull('sucursal_id')->update([
            'sucursal_id' => $casaMatriz->id,
            'punto_venta_id' => $puntoPrincipal->id,
            'codigo_sucursal' => 0,
            'codigo_punto_venta' => 0,
        ]);

        // Vincular eventos existentes
        EventoContingencia::whereNull('sucursal_id')->update([
            'sucursal_id' => $casaMatriz->id,
            'punto_venta_id' => $puntoPrincipal->id,
            'codigo_sucursal' => 0,
            'codigo_punto_venta' => 0,
        ]);
    }
}
