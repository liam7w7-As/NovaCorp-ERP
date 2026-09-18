<?php

namespace Tests\Feature;

use App\Models\CuotaVenta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reportes_muestra_alertas_operativas_accionables(): void
    {
        $this->travelTo('2026-09-16 10:00:00');
        $admin = User::factory()->create([
            'rol' => 'admin',
            'activo' => true,
        ]);
        $productoSinFicha = Producto::create([
            'codigo' => 'REP-FICHA-1',
            'descripcion' => 'Bomba sin ficha',
            'marca' => 'Giseca',
            'costo' => 80,
            'precio' => 120,
            'stock' => 8,
            'stock_min' => 2,
        ]);
        $ventaVencida = Venta::create([
            'numero' => 'REP-COB-1',
            'tipo' => 'sin_factura',
            'modalidad' => 'credito',
            'cliente_nombre' => 'Cliente Vencido Reporte',
            'fecha' => '2026-09-01',
            'credito_dias' => 7,
            'credito_cuotas' => 1,
            'fecha_vencimiento' => '2026-09-08',
            'subtotal' => 200,
            'total' => 200,
            'pagado' => 50,
            'estado' => 'activa',
            'entrega_estado' => 'entregada',
        ]);
        CuotaVenta::create([
            'venta_id' => $ventaVencida->id,
            'numero' => 1,
            'fecha_vencimiento' => '2026-09-08',
            'monto' => 200,
            'pagado' => 50,
            'estado' => 'parcial',
        ]);
        $ventaPorVencer = Venta::create([
            'numero' => 'REP-SEM-1',
            'tipo' => 'sin_factura',
            'modalidad' => 'credito',
            'cliente_nombre' => 'Cliente Semana Reporte',
            'fecha' => '2026-09-10',
            'credito_dias' => 10,
            'credito_cuotas' => 1,
            'fecha_vencimiento' => '2026-09-20',
            'subtotal' => 100,
            'total' => 100,
            'pagado' => 0,
            'estado' => 'activa',
            'entrega_estado' => 'entregada',
        ]);
        CuotaVenta::create([
            'venta_id' => $ventaPorVencer->id,
            'numero' => 1,
            'fecha_vencimiento' => '2026-09-20',
            'monto' => 100,
            'pagado' => 0,
            'estado' => 'pendiente',
        ]);
        $ventaEntrega = Venta::create([
            'numero' => 'REP-ENT-1',
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'cliente_nombre' => 'Cliente Entrega Reporte',
            'fecha' => '2026-09-15',
            'subtotal' => 480,
            'total' => 480,
            'pagado' => 480,
            'estado' => 'activa',
            'entrega_estado' => 'pendiente',
        ]);
        DetalleVenta::create([
            'venta_id' => $ventaEntrega->id,
            'producto_id' => $productoSinFicha->id,
            'codigo_producto' => $productoSinFicha->codigo,
            'descripcion_producto' => $productoSinFicha->descripcion,
            'cantidad' => 4,
            'cantidad_entregada' => 1,
            'precio_unitario' => 120,
            'subtotal' => 480,
        ]);

        $response = $this->actingAs($admin)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertSee('Alertas operativas', false);
        $response->assertSee('Bs 250.00', false);
        $response->assertSee('Bs 150.00', false);
        $response->assertSee('Cobranzas críticas', false);
        $response->assertSee('REP-COB-1', false);
        $response->assertSee('Cliente Vencido Reporte', false);
        $response->assertSee('Vencida', false);
        $response->assertSee('REP-SEM-1', false);
        $response->assertSee('Por vencer', false);
        $response->assertSee('Entregas pendientes', false);
        $response->assertSee('REP-ENT-1', false);
        $response->assertSee('Fichas por completar', false);
        $response->assertSee('Bomba sin ficha', false);
        $response->assertSee(route('cuentas.index', ['q' => $ventaVencida->numero]), false);
        $response->assertSee(route('almacen.show', $ventaEntrega), false);
        $response->assertSee(route('productos.index', ['q' => $productoSinFicha->codigo]), false);
    }
}
