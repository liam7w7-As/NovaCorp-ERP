<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaCobroTest extends TestCase
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

    protected function crearProducto(): Producto
    {
        return Producto::create([
            'codigo' => 'CAJA-'.uniqid(),
            'descripcion' => 'Producto caja test',
            'costo' => 10,
            'precio' => 20,
            'stock' => 100,
        ]);
    }

    protected function crearVentaCredito(Producto $producto, float $cantidad = 5): Venta
    {
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'cliente_nuevo' => 'Cliente Caja Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'credito',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio' => 20],
            ],
        ])->assertSessionHas('exito');

        return Venta::latest('id')->firstOrFail();
    }

    protected function crearCompraCredito(Producto $producto, float $cantidad = 5): Compra
    {
        $this->actingAs($this->admin)->post(route('compras.store'), [
            'proveedor_nuevo' => 'Proveedor Caja Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'credito',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'costo' => 10],
            ],
        ])->assertSessionHas('exito');

        return Compra::latest('id')->firstOrFail();
    }

    public function test_cobro_parcial_registra_comprobante_y_suma_pagado(): void
    {
        $venta = $this->crearVentaCredito($this->crearProducto());
        $this->assertEquals(0, (float) $venta->fresh()->pagado);

        $comprobantesAntes = Comprobante::count();

        $this->actingAs($this->admin)
            ->post(route('cuentas.cobrar', $venta), ['monto' => 40])
            ->assertSessionHas('exito');

        $this->assertEquals(40, (float) $venta->fresh()->pagado);
        $this->assertSame($comprobantesAntes + 1, Comprobante::count());
    }

    public function test_sobre_cobro_es_rechazado_sin_efectos(): void
    {
        $venta = $this->crearVentaCredito($this->crearProducto());
        $comprobantesAntes = Comprobante::count();

        $this->actingAs($this->admin)
            ->post(route('cuentas.cobrar', $venta), ['monto' => 150])
            ->assertSessionHas('error');

        $this->assertEquals(0, (float) $venta->fresh()->pagado);
        $this->assertSame($comprobantesAntes, Comprobante::count());
    }

    public function test_doble_cobro_nunca_supera_el_total(): void
    {
        $venta = $this->crearVentaCredito($this->crearProducto());

        $this->actingAs($this->admin)
            ->post(route('cuentas.cobrar', $venta), ['monto' => 100])
            ->assertSessionHas('exito');

        $this->actingAs($this->admin)
            ->post(route('cuentas.cobrar', $venta), ['monto' => 10])
            ->assertSessionHas('error');

        $this->assertEquals(100, (float) $venta->fresh()->pagado);
    }

    public function test_pago_parcial_y_sobre_pago_de_compra(): void
    {
        $compra = $this->crearCompraCredito($this->crearProducto());

        $this->actingAs($this->admin)
            ->post(route('cuentas.pagar', $compra), ['monto' => 20])
            ->assertSessionHas('exito');
        $this->assertEquals(20, (float) $compra->fresh()->pagado);

        // Saldo restante 30, intentar 50 debe fallar
        $this->actingAs($this->admin)
            ->post(route('cuentas.pagar', $compra), ['monto' => 50])
            ->assertSessionHas('error');
        $this->assertEquals(20, (float) $compra->fresh()->pagado);
    }
}
