<?php

namespace Tests\Feature;

use App\Models\CobroVenta;
use App\Models\Comprobante;
use App\Models\CuotaVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CobranzasCreditoTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create([
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    protected function producto(): Producto
    {
        return Producto::create([
            'codigo' => 'COB-'.uniqid(),
            'descripcion' => 'Producto cobranza test',
            'costo' => 20,
            'precio' => 50,
            'stock' => 100,
        ]);
    }

    protected function crearVentaCredito(User $admin, array $extra = []): Venta
    {
        $producto = $this->producto();

        $payload = [
            'cliente_nuevo' => 'Cliente Cobranzas Test',
            'tipo' => 'sin_factura',
            'modalidad' => 'credito',
            'fecha' => '2026-09-01',
            'credito_dias' => 15,
            'credito_cuotas' => 2,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 50],
            ],
            ...$extra,
        ];

        $this->actingAs($admin)
            ->post(route('ventas.store'), $payload)
            ->assertSessionHas('exito');

        return Venta::with('cuotas')->latest('id')->firstOrFail();
    }

    public function test_venta_credito_crea_plan_de_cuotas_y_vencimientos(): void
    {
        $venta = $this->crearVentaCredito($this->admin());

        $this->assertSame('credito', $venta->modalidad);
        $this->assertSame(15, $venta->credito_dias);
        $this->assertSame(2, $venta->credito_cuotas);
        $this->assertSame('2026-09-16', $venta->fecha_vencimiento->format('Y-m-d'));
        $this->assertSame(0.0, (float) $venta->pagado);
        $this->assertCount(2, $venta->cuotas);

        $primera = $venta->cuotas->first();
        $segunda = $venta->cuotas->last();
        $this->assertSame(1, $primera->numero);
        $this->assertSame(50.0, (float) $primera->monto);
        $this->assertSame('2026-09-16', $primera->fecha_vencimiento->format('Y-m-d'));
        $this->assertSame(2, $segunda->numero);
        $this->assertSame(50.0, (float) $segunda->monto);
        $this->assertSame('2026-10-01', $segunda->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_cobro_de_cuota_actualiza_cuota_venta_y_abre_recibo(): void
    {
        $admin = $this->admin();
        $venta = $this->crearVentaCredito($admin);
        $cuota = $venta->cuotas->first();
        $comprobantesAntes = Comprobante::count();

        $response = $this->actingAs($admin)->post(route('cuentas.cobrar', $venta), [
            'cuota_id' => $cuota->id,
            'monto' => 50,
            'fecha' => '2026-09-20',
            'metodo' => 'QR',
            'referencia' => 'QR-123',
            'observaciones' => 'Pago primera cuota',
        ]);

        $cobro = CobroVenta::with(['cuota', 'comprobante'])->firstOrFail();
        $response->assertRedirect(route('cuentas.recibo', $cobro));
        $response->assertSessionHas('exito');
        $this->assertSame($venta->id, $cobro->venta_id);
        $this->assertSame($cuota->id, $cobro->cuota_venta_id);
        $this->assertSame(50.0, (float) $cobro->monto);
        $this->assertSame('QR-123', $cobro->referencia);
        $this->assertSame('pagada', $cuota->fresh()->estado);
        $this->assertSame(50.0, (float) $venta->fresh()->pagado);
        $this->assertSame($comprobantesAntes + 1, Comprobante::count());
        $this->assertSame($venta->id, $cobro->comprobante->origen_venta_id);
    }

    public function test_cuentas_muestra_estado_vencida_y_totales(): void
    {
        $this->travelTo('2026-09-16 10:00:00');
        $venta = $this->crearVentaCredito($this->admin(), [
            'fecha' => '2026-08-01',
            'credito_dias' => 15,
            'credito_cuotas' => 1,
        ]);

        $response = $this->actingAs($this->admin())->get(route('cuentas.index'));

        $response->assertOk();
        $response->assertSee($venta->numero, false);
        $response->assertSee('Vencida', false);
        $response->assertSee('Bs 100.00', false);
        $this->assertSame(100.0, (float) CuotaVenta::firstOrFail()->saldo);
    }

    public function test_ventas_index_muestra_cobranza_y_enlace_filtrado_a_cuentas(): void
    {
        $this->travelTo('2026-09-16 10:00:00');
        $admin = $this->admin();
        $venta = $this->crearVentaCredito($admin, [
            'fecha' => '2026-08-01',
            'credito_dias' => 15,
            'credito_cuotas' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('ventas.index'));

        $response->assertOk();
        $response->assertSee('Cobranza', false);
        $response->assertSee('Vencida', false);
        $response->assertSee('Saldo Bs 100.00', false);
        $response->assertSee(route('cuentas.index', ['q' => $venta->numero]), false);
    }

    public function test_cuentas_filtra_por_numero_de_venta_desde_ventas(): void
    {
        $admin = $this->admin();
        $ventaVisible = $this->crearVentaCredito($admin);
        $this->crearVentaCredito($admin, ['cliente_nuevo' => 'Cliente Oculto Cobranzas']);

        $response = $this->actingAs($admin)->get(route('cuentas.index', ['q' => $ventaVisible->numero]));

        $response->assertOk();
        $response->assertSee($ventaVisible->numero, false);
        $response->assertDontSee('Cliente Oculto Cobranzas', false);
    }
}
