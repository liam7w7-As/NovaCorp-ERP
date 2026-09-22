<?php

namespace Tests\Feature;

use App\Models\CatalogoSin;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomologacionProductoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Homologacion',
            'email' => 'homologacion@giseca.test',
            'password' => bcrypt('secret123'),
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_sugiere_codigo_sin_por_descripcion_sin_aplicarlo_automaticamente(): void
    {
        $producto = Producto::create([
            'codigo' => 'LF-9009',
            'descripcion' => 'Filtro de aceite para motor Cummins',
            'marca' => 'Fleetguard',
        ]);
        CatalogoSin::create([
            'tipo' => 'producto',
            'codigo' => '32110',
            'descripcion' => 'Filtros de aceite y combustible para motores',
            'extra' => ['actividad_economica' => '4530000'],
        ]);
        CatalogoSin::create([
            'tipo' => 'producto',
            'codigo' => '44110',
            'descripcion' => 'Pinturas, esmaltes y barnices',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('productos.homologacion.sugerencias'));

        $response->assertOk()
            ->assertJsonPath('items.0.id', $producto->id)
            ->assertJsonPath('items.0.sugerencias.0.codigo', '32110')
            ->assertJsonPath('items.0.sugerencias.0.actividad_economica', '4530000');

        $this->assertNull($producto->fresh()->codigo_sin);
    }

    public function test_usuario_confirma_homologacion_asistida_en_lote(): void
    {
        $producto = Producto::create([
            'codigo' => 'FF-5320',
            'descripcion' => 'Filtro de combustible',
        ]);
        CatalogoSin::create([
            'tipo' => 'producto',
            'codigo' => '32110',
            'descripcion' => 'Filtros de aceite y combustible para motores',
            'extra' => ['actividad_economica' => '4530000'],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('productos.homologacion.store'), [
            'homologaciones' => [[
                'producto_id' => $producto->id,
                'codigo_sin' => '32110',
                'unidad_sin' => '58',
                'confianza' => 82,
            ]],
        ]);

        $response->assertOk()->assertJsonPath('actualizados', 1);
        $producto->refresh();
        $this->assertSame('32110', $producto->codigo_sin);
        $this->assertSame('4530000', $producto->actividad_economica_sin);
        $this->assertSame('58', $producto->unidad_sin);
        $this->assertSame('confirmada', $producto->homologacion_estado);
        $this->assertSame(82, $producto->homologacion_confianza);
        $this->assertSame($this->admin->id, $producto->homologado_por);
        $this->assertNotNull($producto->homologado_at);
    }

    public function test_rechaza_codigo_que_no_proviene_del_catalogo_sin(): void
    {
        $producto = Producto::create([
            'codigo' => 'P-001',
            'descripcion' => 'Producto de prueba',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('productos.homologacion.store'), [
            'homologaciones' => [[
                'producto_id' => $producto->id,
                'codigo_sin' => 'INVENTADO',
                'unidad_sin' => '58',
            ]],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('homologaciones.0.codigo_sin');
        $this->assertNull($producto->fresh()->codigo_sin);
    }
}
