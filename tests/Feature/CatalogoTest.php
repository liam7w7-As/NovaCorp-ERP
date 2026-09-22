<?php

namespace Tests\Feature;

use App\Models\CatalogoSin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoTest extends TestCase
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

    public function test_usuario_autenticado_puede_ver_catalogos(): void
    {
        $response = $this->actingAs($this->admin)->get(route('catalogos.index'));

        $response->assertStatus(200);
        $response->assertSee('Catálogos y Tablas Paramétricas SIN');
        $response->assertSee('Actividades económicas');
        $response->assertSee('Unidades de medida');
    }

    public function test_sincronizacion_todos_los_catalogos(): void
    {
        $response = $this->actingAs($this->admin)->post(route('catalogos.sincronizar'), []);

        $response->assertSessionHas('exito');
        $this->assertGreaterThan(20, CatalogoSin::count());
    }

    public function test_sincronizacion_catalogo_especifico_leyenda(): void
    {
        $response = $this->actingAs($this->admin)->post(route('catalogos.sincronizar'), [
            'tipo' => 'leyenda',
        ]);

        $response->assertSessionHas('exito');
        $this->assertGreaterThan(0, CatalogoSin::where('tipo', 'leyenda')->count());
    }

    public function test_sincronizacion_reemplaza_codigos_obsoletos_del_mismo_catalogo(): void
    {
        CatalogoSin::create([
            'tipo' => 'actividad',
            'codigo' => '9999999',
            'descripcion' => 'Código obsoleto',
        ]);

        $this->actingAs($this->admin)->post(route('catalogos.sincronizar'), [
            'tipo' => 'actividad',
        ])->assertSessionHas('exito');

        $this->assertDatabaseMissing('catalogos_sin', [
            'tipo' => 'actividad',
            'codigo' => '9999999',
        ]);
        $this->assertGreaterThan(0, CatalogoSin::where('tipo', 'actividad')->count());
    }

    public function test_busqueda_productos_sin(): void
    {
        // Asegurar que exista al menos un producto
        CatalogoSin::updateOrCreate(
            ['tipo' => 'producto', 'codigo' => '32110'],
            ['descripcion' => 'Filtros de aceite y combustible']
        );

        $response = $this->actingAs($this->admin)->get(route('catalogos.productos', ['q' => 'filtro']));

        $response->assertStatus(200);
        $response->assertJsonFragment(['codigo' => '32110']);
    }

    public function test_busqueda_unidades_sin(): void
    {
        CatalogoSin::updateOrCreate(
            ['tipo' => 'unidad', 'codigo' => '58'],
            ['descripcion' => 'UNIDAD (SERVICIOS)']
        );

        $response = $this->actingAs($this->admin)->get(route('catalogos.unidades', ['q' => '58']));

        $response->assertStatus(200);
        $response->assertJsonFragment(['codigo' => '58']);
    }
}
