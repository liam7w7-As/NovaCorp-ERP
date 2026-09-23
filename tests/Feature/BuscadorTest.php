<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuscadorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );
    }

    public function test_buscar_clientes_devuelve_datos_de_contacto(): void
    {
        Cliente::create([
            'nombre' => 'Transportes XYZ', 'nit' => '1234567',
            'telefono' => '70011122', 'correo' => 'x@xyz.bo', 'direccion' => 'Av. Siempre Viva',
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('clientes.buscar', ['q' => 'XYZ']));

        $response->assertOk()->assertJsonPath('0.nombre', 'Transportes XYZ');
        $response->assertJsonPath('0.telefono', '70011122');
        $response->assertJsonPath('0.correo', 'x@xyz.bo');
        $response->assertJsonPath('0.direccion', 'Av. Siempre Viva');
    }

    public function test_buscar_clientes_por_telefono(): void
    {
        Cliente::create(['nombre' => 'Sin Nombre', 'telefono' => '76543210']);

        $this->actingAs($this->admin)->getJson(route('clientes.buscar', ['q' => '76543210']))
            ->assertOk()
            ->assertJsonPath('0.telefono', '76543210');
    }

    public function test_buscar_proveedores_devuelve_telefono(): void
    {
        Proveedor::create(['nombre' => 'Prov Tel', 'telefono' => '33221100']);

        $this->actingAs($this->admin)->getJson(route('proveedores.buscar', ['q' => 'Prov Tel']))
            ->assertOk()
            ->assertJsonPath('0.telefono', '33221100');
    }

    public function test_formularios_cargan_con_datos_para_tomselect(): void
    {
        Cliente::create(['nombre' => 'Cli Form', 'nit' => '111', 'telefono' => '70000001']);
        Proveedor::create(['nombre' => 'Prov Form', 'telefono' => '33221100']);

        foreach ([route('ventas.create'), route('compras.create'), route('proformas.create')] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee('data-data', false);
        }
    }
}
