<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DescuentoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );
        $this->producto = Producto::create([
            'codigo' => 'DCT-1', 'descripcion' => 'Prod desc',
            'costo' => 10, 'precio' => 50, 'stock' => 100,
        ]);
    }

    public function test_venta_con_porcentaje(): void
    {
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'cliente_nuevo' => 'Cli Desc',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'descuento' => 10,
            'descuento_tipo' => 'porcentaje',
            'items' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio' => 50],
            ],
        ])->assertSessionHas('exito');

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame('porcentaje', $venta->descuento_tipo);
        $this->assertEquals(90, (float) $venta->total);
    }

    public function test_venta_fijo_sigue_igual(): void
    {
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'cliente_nuevo' => 'Cli Fijo',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'descuento' => 15,
            'items' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio' => 50],
            ],
        ])->assertSessionHas('exito');

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame('fijo', $venta->descuento_tipo);
        $this->assertEquals(85, (float) $venta->total);
    }

    public function test_porcentaje_mayor_a_100_se_topa(): void
    {
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'cliente_nuevo' => 'Cli Tope',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'descuento' => 150,
            'descuento_tipo' => 'porcentaje',
            'items' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio' => 50],
            ],
        ])->assertSessionHas('exito');

        $this->assertEquals(0, (float) Venta::latest('id')->firstOrFail()->total);
    }

    public function test_compra_y_proforma_con_porcentaje(): void
    {
        $this->actingAs($this->admin)->post(route('compras.store'), [
            'proveedor_nuevo' => 'Prov Desc',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'descuento' => 20,
            'descuento_tipo' => 'porcentaje',
            'items' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 2, 'costo' => 50],
            ],
        ])->assertSessionHas('exito');
        $compra = Compra::latest('id')->firstOrFail();
        $this->assertSame('porcentaje', $compra->descuento_tipo);
        $this->assertEquals(80, (float) $compra->total);

        $this->actingAs($this->admin)->post(route('proformas.store'), [
            'cliente_nuevo' => 'Cli Prof',
            'fecha' => now()->toDateString(),
            'descuento' => 10,
            'descuento_tipo' => 'porcentaje',
            'items' => [
                ['producto_id' => $this->producto->id, 'cantidad' => 2, 'precio' => 50],
            ],
        ])->assertSessionHas('exito');
        $proforma = Proforma::latest('id')->firstOrFail();
        $this->assertSame('porcentaje', $proforma->descuento_tipo);
        $this->assertEquals(90, (float) $proforma->total);
    }
}
