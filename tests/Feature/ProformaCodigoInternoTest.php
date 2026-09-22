<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProformaCodigoInternoTest extends TestCase
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

    public function test_store_guarda_codigo_interno_y_show_lo_muestra(): void
    {
        $producto = Producto::create([
            'codigo' => 'PCI-1', 'descripcion' => 'Prod interno',
            'costo' => 10, 'precio' => 20, 'stock' => 50,
        ]);
        $this->assertNotEmpty($producto->fresh()->codigo_interno);

        $this->actingAs($this->admin)->post(route('proformas.store'), [
            'cliente_nuevo' => 'Cliente Proforma',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 20],
            ],
        ])->assertSessionHas('exito');

        $proforma = Proforma::latest('id')->firstOrFail();
        $detalle = $proforma->detalles()->firstOrFail();
        $this->assertSame($producto->fresh()->codigo_interno, $detalle->codigo_interno);

        $this->actingAs($this->admin)->get(route('proformas.show', $proforma))
            ->assertOk()
            ->assertSee('Cód. Interno', false)
            ->assertSee($detalle->codigo_interno, false);
    }
}
