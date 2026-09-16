<?php

namespace Tests\Feature;

use App\Mail\FacturaCorreo;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\FacturaElectronica;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\FacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FacturaImpresionYReversionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $sucursal;

    protected PuntoVenta $pos;

    protected FacturaElectronica $factura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );

        $this->sucursal = Sucursal::firstOrCreate(
            ['codigo' => 0],
            [
                'nombre' => 'Casa Matriz Central',
                'direccion' => 'Calle Principal 100',
                'municipio' => 'Santa Cruz',
                'telefono' => '33445566',
                'activa' => true,
            ]
        );

        $this->pos = PuntoVenta::firstOrCreate(
            ['sucursal_id' => $this->sucursal->id, 'codigo' => 0],
            [
                'nombre' => 'Caja Central 0',
                'tipo_punto_venta' => 'Punto de Venta Fijo',
                'cuis' => 'CUIS-TEST',
                'cuis_vigencia' => now()->addMonths(6),
                'cufd' => 'CUFD-TEST',
                'cufd_codigo_control' => 'CTRL-TEST',
                'cufd_vigencia' => now()->addDay(),
                'activo' => true,
            ]
        );

        $cliente = Cliente::create([
            'nombre' => 'CONSTRUCTORA SANTA CRUZ SRL',
            'nit' => '1029384756',
            'tipo_documento' => 'NIT',
            'correo' => 'facturacion@constructora.bo',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'codigo' => 'PROD-CEMENTO',
            'descripcion' => 'Bolsa de Cemento IP-30',
            'costo' => 45.00,
            'precio' => 60.00,
            'stock' => 100,
            'stock_min' => 10,
            'codigo_sin' => '32110',
            'unidad_sin' => '58',
        ]);

        $venta = Venta::create([
            'numero' => 'VTA-CEMENTO-01',
            'tipo' => 'con_factura',
            'modalidad' => 'contado',
            'fecha' => now(),
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'nit_cliente' => $cliente->nit,
            'subtotal' => 120.00,
            'descuento' => 0.00,
            'total' => 120.00,
            'debito_fiscal' => 15.60,
            'estado' => 'activa',
            'sucursal_id' => $this->sucursal->id,
            'punto_venta_id' => $this->pos->id,
            'codigo_sucursal' => 0,
            'codigo_punto_venta' => 0,
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo,
            'descripcion_producto' => $producto->descripcion,
            'cantidad' => 2,
            'precio_unitario' => 60.00,
            'subtotal' => 120.00,
        ]);

        /** @var FacturaService $service */
        $service = app(FacturaService::class);
        $this->factura = $service->emitirDesdeVenta($venta, $this->admin->id);
    }

    public function test_descarga_pdf_carta(): void
    {
        $response = $this->actingAs($this->admin)->get(route('facturas.pdf', $this->factura));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_descarga_pdf_medio_oficio(): void
    {
        $response = $this->actingAs($this->admin)->get(route('facturas.pdf-medio-oficio', $this->factura));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('medio-oficio.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_descarga_pdf_rollo_80mm(): void
    {
        $response = $this->actingAs($this->admin)->get(route('facturas.pdf-rollo', $this->factura));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('rollo-80mm.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_descarga_pdf_rollo_58mm(): void
    {
        $response = $this->actingAs($this->admin)->get(route('facturas.pdf-rollo-58', $this->factura));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('rollo-58mm.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_anulacion_y_reversion_valida_dentro_del_plazo(): void
    {
        /** @var FacturaService $service */
        $service = app(FacturaService::class);

        // 1. Anular
        $facturaAnulada = $service->anular($this->factura, 1);
        $this->assertEquals('anulada', $facturaAnulada->estado);

        // 2. Revertir dentro de plazo
        $facturaRevertida = $service->revertirAnulacion($facturaAnulada);
        $this->assertEquals('emitida', $facturaRevertida->estado);
    }

    public function test_reversion_rechazada_fuera_del_plazo_legal(): void
    {
        /** @var FacturaService $service */
        $service = app(FacturaService::class);

        // Anular
        $facturaAnulada = $service->anular($this->factura, 1);

        // Simular que fue emitida hace 3 meses
        $facturaAnulada->update([
            'fecha_emision' => now()->subMonths(3),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El plazo legal según normativa SIN para revertir la anulación venció');

        $service->revertirAnulacion($facturaAnulada);
    }

    public function test_reenvio_de_correo_con_adjuntos(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->post(route('facturas.enviar-correo', $this->factura), [
            'email' => 'cliente_nuevo@empresa.bo',
        ]);

        $response->assertSessionHas('exito');
        Mail::assertSent(FacturaCorreo::class, function (FacturaCorreo $mail) {
            return $mail->hasTo('cliente_nuevo@empresa.bo') &&
                   $mail->factura->id === $this->factura->id;
        });
    }

    public function test_reporte_anulaciones_csv_con_sucursal(): void
    {
        /** @var FacturaService $service */
        $service = app(FacturaService::class);
        $service->anular($this->factura, 1);

        $response = $this->actingAs($this->admin)->get(route('facturas.reporte'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Numero,CUF,Sucursal,PuntoVenta,Cliente', $content);
        $this->assertStringContainsString($this->factura->numero_factura, $content);
        $this->assertStringContainsString('Casa Matriz Central', $content);
    }
}
