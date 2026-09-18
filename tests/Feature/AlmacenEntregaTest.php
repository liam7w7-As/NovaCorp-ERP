<?php

namespace Tests\Feature;

use App\Models\DetalleVenta;
use App\Models\NotaEntrega;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlmacenEntregaTest extends TestCase
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
            'codigo' => 'ALM-'.uniqid(),
            'descripcion' => 'Producto almacén test',
            'costo' => 10,
            'precio' => 20,
            'stock' => $stock,
        ]);
    }

    protected function crearVentaReservada(Producto $producto, float $cantidad = 4): DetalleVenta
    {
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'cliente_nuevo' => 'Cliente Almacén Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio' => 20],
            ],
        ])->assertSessionHas('exito');

        return DetalleVenta::firstOrFail();
    }

    public function test_emitir_nota_entrega_descuenta_stock_fisico_y_libera_reserva(): void
    {
        $producto = $this->crearProducto(10);
        $detalle = $this->crearVentaReservada($producto, 4);

        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(4, (float) $producto->stock_reservado);

        $venta = $detalle->venta;
        $this->actingAs($this->admin)->post(route('almacen.entregar', $venta), [
            'fecha' => now()->toDateString(),
            'cantidades' => [$detalle->id => 2],
        ])->assertSessionHas('exito');

        $producto->refresh();
        $detalle->refresh();
        $venta->refresh();

        $this->assertEquals(8, (float) $producto->stock);
        $this->assertEquals(2, (float) $producto->stock_reservado);
        $this->assertEquals(2, (float) $detalle->cantidad_entregada);
        $this->assertEquals(2, (float) $detalle->pendiente_entrega);
        $this->assertSame('parcial', $venta->entrega_estado);
        $this->assertSame(1, NotaEntrega::count());
    }

    public function test_anular_nota_entrega_devuelve_la_salida_a_reserva(): void
    {
        $producto = $this->crearProducto(10);
        $detalle = $this->crearVentaReservada($producto, 4);
        $venta = $detalle->venta;

        $this->actingAs($this->admin)->post(route('almacen.entregar', $venta), [
            'fecha' => now()->toDateString(),
            'cantidades' => [$detalle->id => 4],
        ])->assertSessionHas('exito');

        $nota = NotaEntrega::firstOrFail();
        $producto->refresh();
        $this->assertEquals(6, (float) $producto->stock);
        $this->assertEquals(0, (float) $producto->stock_reservado);
        $this->assertSame('entregada', $venta->fresh()->entrega_estado);

        $this->actingAs($this->admin)->post(route('almacen.notas.anular', $nota))
            ->assertSessionHas('exito');

        $producto->refresh();
        $detalle->refresh();
        $venta->refresh();

        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(4, (float) $producto->stock_reservado);
        $this->assertEquals(0, (float) $detalle->cantidad_entregada);
        $this->assertSame('pendiente', $venta->entrega_estado);
        $this->assertSame('anulada', $nota->fresh()->estado);
    }
}
