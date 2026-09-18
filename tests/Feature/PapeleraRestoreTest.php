<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PapeleraRestoreTest extends TestCase
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
            'codigo' => 'PAP-'.uniqid(),
            'descripcion' => 'Producto papelera test',
            'costo' => 10,
            'precio' => 20,
            'stock' => $stock,
        ]);
    }

    protected function crearVenta(Producto $producto, float $cantidad): Venta
    {
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'cliente_nuevo' => 'Cliente Papelera Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio' => 20],
            ],
        ])->assertSessionHas('exito');

        return Venta::latest('id')->firstOrFail();
    }

    public function test_restaurar_venta_reaplica_reserva_y_comprobante(): void
    {
        $producto = $this->crearProducto(10);
        $venta = $this->crearVenta($producto, 4);
        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(4, (float) $producto->stock_reservado);

        $this->actingAs($this->admin)->delete(route('ventas.destroy', $venta))
            ->assertSessionHas('exito');
        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(0, (float) $producto->stock_reservado);
        $this->assertTrue($venta->fresh()->trashed());
        $this->assertSame(1, Comprobante::onlyTrashed()->where('origen_venta_id', $venta->id)->count());

        $this->actingAs($this->admin)
            ->post(route('papelera.restaurar', ['modelo' => 'ventas', 'id' => $venta->id]))
            ->assertSessionHas('exito');

        $this->assertFalse($venta->fresh()->trashed());
        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(4, (float) $producto->stock_reservado);
        $this->assertSame(0, Comprobante::onlyTrashed()->where('origen_venta_id', $venta->id)->count());
    }

    public function test_restaurar_venta_bloqueada_sin_stock_suficiente(): void
    {
        $producto = $this->crearProducto(10);
        $venta = $this->crearVenta($producto, 10);

        $this->actingAs($this->admin)->delete(route('ventas.destroy', $venta));
        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(0, (float) $producto->stock_reservado);

        // Otra venta reserva el stock liberado.
        $this->crearVenta($producto, 10);
        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(10, (float) $producto->stock_reservado);

        $this->actingAs($this->admin)
            ->post(route('papelera.restaurar', ['modelo' => 'ventas', 'id' => $venta->id]))
            ->assertSessionHas('error');

        $this->assertTrue($venta->fresh()->trashed());
        $producto->refresh();
        $this->assertEquals(10, (float) $producto->stock);
        $this->assertEquals(10, (float) $producto->stock_reservado);
    }

    public function test_restaurar_compra_revierte_stock_y_comprobante(): void
    {
        $producto = $this->crearProducto(0);

        $this->actingAs($this->admin)->post(route('compras.store'), [
            'proveedor_nuevo' => 'Proveedor Papelera Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 8, 'costo' => 10],
            ],
        ])->assertSessionHas('exito');
        $compra = Compra::latest('id')->firstOrFail();
        $this->assertEquals(8, (float) $producto->fresh()->stock);

        $this->actingAs($this->admin)->delete(route('compras.destroy', $compra));
        $this->assertEquals(0, (float) $producto->fresh()->stock);

        $this->actingAs($this->admin)
            ->post(route('papelera.restaurar', ['modelo' => 'compras', 'id' => $compra->id]))
            ->assertSessionHas('exito');

        $this->assertFalse($compra->fresh()->trashed());
        $this->assertEquals(8, (float) $producto->fresh()->stock);
        $this->assertSame(0, Comprobante::onlyTrashed()->where('origen_compra_id', $compra->id)->count());
    }
}
