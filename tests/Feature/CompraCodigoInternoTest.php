<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraCodigoInternoTest extends TestCase
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

    public function test_store_guarda_codigo_interno_y_edit_lo_muestra(): void
    {
        $producto = Producto::create([
            'codigo' => 'CCI-1', 'descripcion' => 'Prod compra',
            'costo' => 10, 'precio' => 20, 'stock' => 0,
        ]);
        $interno = $producto->fresh()->codigo_interno;
        $this->assertNotEmpty($interno);

        $this->actingAs($this->admin)->post(route('compras.store'), [
            'proveedor_nuevo' => 'Prov Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 5, 'costo' => 10],
            ],
        ])->assertSessionHas('exito');

        $compra = Compra::latest('id')->firstOrFail();
        $this->assertSame($interno, $compra->detalles()->firstOrFail()->codigo_interno);

        $this->actingAs($this->admin)->get(route('compras.edit', $compra))
            ->assertOk()
            ->assertSee('Cód. Interno', false)
            ->assertSee($interno, false);
    }
}
