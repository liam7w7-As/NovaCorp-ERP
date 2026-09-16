<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaStockTest extends TestCase
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

    protected function crearProducto(float $stock = 10): Producto
    {
        return Producto::create([
            'codigo' => 'STK-'.uniqid(),
            'descripcion' => 'Producto stock test',
            'costo' => 10,
            'precio' => 20,
            'stock' => $stock,
        ]);
    }

    protected function payloadVenta(Producto $producto, float $cantidad): array
    {
        return [
            'cliente_nuevo' => 'Cliente Stock Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio' => 20],
            ],
        ];
    }

    public function test_crear_venta_descuenta_stock(): void
    {
        $producto = $this->crearProducto(10);

        $response = $this->actingAs($this->admin)->post(route('ventas.store'), $this->payloadVenta($producto, 3));

        $response->assertSessionHas('exito');
        $this->assertEquals(7, (float) $producto->fresh()->stock);
    }

    public function test_venta_sin_stock_suficiente_es_rechazada(): void
    {
        $producto = $this->crearProducto(2);

        $response = $this->actingAs($this->admin)->post(route('ventas.store'), $this->payloadVenta($producto, 5));

        $response->assertSessionHas('error');
        $this->assertEquals(2, (float) $producto->fresh()->stock);
        $this->assertSame(0, Venta::count());
    }

    public function test_segunda_venta_no_puede_sobrevender(): void
    {
        $producto = $this->crearProducto(5);

        $this->actingAs($this->admin)->post(route('ventas.store'), $this->payloadVenta($producto, 5))
            ->assertSessionHas('exito');
        $this->assertEquals(0, (float) $producto->fresh()->stock);

        $this->actingAs($this->admin)->post(route('ventas.store'), $this->payloadVenta($producto, 1))
            ->assertSessionHas('error');
        $this->assertEquals(0, (float) $producto->fresh()->stock);
        $this->assertSame(1, Venta::count());
    }

    public function test_anular_venta_revierte_stock(): void
    {
        $producto = $this->crearProducto(10);
        $this->actingAs($this->admin)->post(route('ventas.store'), $this->payloadVenta($producto, 4));

        $venta = Venta::firstOrFail();
        $this->actingAs($this->admin)->post(route('ventas.anular', $venta))
            ->assertSessionHas('exito');

        $this->assertEquals(10, (float) $producto->fresh()->stock);
        $this->assertSame('anulada', $venta->fresh()->estado);
    }

    public function test_eliminar_compra_no_deja_stock_negativo(): void
    {
        $producto = $this->crearProducto(0);

        // Compra +10
        $this->actingAs($this->admin)->post(route('compras.store'), [
            'proveedor_nuevo' => 'Proveedor Stock Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 10, 'costo' => 10],
            ],
        ])->assertSessionHas('exito');
        $this->assertEquals(10, (float) $producto->fresh()->stock);

        // Vende las 10 → stock 0
        $this->actingAs($this->admin)->post(route('ventas.store'), $this->payloadVenta($producto, 10))
            ->assertSessionHas('exito');

        // Eliminar la compra debe bloquearse, no dejar negativo
        $compra = Compra::firstOrFail();
        $this->actingAs($this->admin)->delete(route('compras.destroy', $compra))
            ->assertSessionHas('error');

        $this->assertEquals(0, (float) $producto->fresh()->stock);
        $this->assertNotNull(Compra::find($compra->id));
    }
}
