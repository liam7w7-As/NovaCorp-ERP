<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\DetalleVenta;
use App\Models\EventoSiat;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\FacturaService;
use App\Services\SiatConfig;
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiatEmisionTest extends TestCase
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
        Configuracion::set('siat_nit', '1020304050', 'text');
        Configuracion::set('siat_razon_social', 'EMPRESA TEST SRL', 'text');
    }

    // ------------------------------------------------------------ CUF real

    public function test_modulo11_y_decimal_a_hex(): void
    {
        $this->assertSame(2, SiatService::modulo11('1'));
        $this->assertSame(0, SiatService::modulo11('0'));
        $this->assertSame('FF', SiatService::decimalAHex('255'));
        $this->assertSame('10', SiatService::decimalAHex('16'));
    }

    public function test_generar_cuf_real_aplica_anchos_fijos(): void
    {
        Configuracion::set('siat_modo', 'real', 'text');
        $siat = new SiatService;
        $args = ['1020304050', '20260115120000000', '5', 2, 1, 1, 1, '7', '2', 'ABC123'];

        $corto = $siat->generarCuf(...$args);
        $largo = $siat->generarCuf('1020304050', '20260115120000000', '0005', 2, 1, 1, '01', '0000000007', '0002', 'ABC123');

        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $corto);
        $this->assertSame($largo, $corto, 'Sucursal, sector, número y PV deben ir con ceros a la izquierda.');
        $this->assertSame($corto, $siat->generarCuf(...$args), 'Mismos insumos → mismo CUF (determinista).');
    }

    public function test_generar_cuf_coincide_con_ejemplo_oficial_del_sin(): void
    {
        Configuracion::set('siat_modo', 'real', 'text');

        $cuf = (new SiatService)->generarCuf(
            '123456789',
            '20190113163721231',
            '0',
            1,
            1,
            1,
            1,
            '1',
            '0',
            'A19E23EF34124CD',
        );

        $this->assertSame('8727F63A15F8976591FDDE5B387C5D015A29E06A1A19E23EF34124CD', $cuf);
    }

    public function test_generar_cuf_coincide_con_factura_real_reportada(): void
    {
        Configuracion::set('siat_modo', 'real', 'text');

        $cuf = (new SiatService)->generarCuf(
            '699765026',
            '20260922115010341',
            '0',
            2,
            1,
            1,
            1,
            '000018',
            '0',
            '220EA1E8833BF74',
        );

        $this->assertSame('2FE13EE6B10B562B048B3BE8B6AC61AF1B029FF745220EA1E8833BF74', $cuf);
    }

    public function test_evento_siat_presenta_resumen_legible_de_la_respuesta_real(): void
    {
        $evento = EventoSiat::registrar(
            'recepcionFactura',
            ['cuf' => 'ABC123', 'solicitud' => ['codigoAmbiente' => 1]],
            [
                'transaccion' => true,
                'codigoRecepcion' => 'recepcion-123',
                'codigoDescripcion' => '',
                'raw' => json_encode([
                    'RespuestaServicioFacturacion' => [
                        'codigoDescripcion' => 'VALIDADA',
                        'codigoEstado' => 908,
                        'codigoRecepcion' => 'recepcion-123',
                        'transaccion' => true,
                    ],
                ]),
            ],
            true,
        );

        $this->assertSame('Recepción de factura', $evento->nombreMetodo());
        $this->assertSame([
            'transaccion' => true,
            'codigo_estado' => '908',
            'codigo_recepcion' => 'recepcion-123',
            'descripcion' => 'VALIDADA',
        ], $evento->resumenRespuesta());
    }

    // ------------------------------------------------------------ Emisión dual

    protected function armarVentaFacturable(?PuntoVenta $pos = null, ?Sucursal $sucursal = null): Venta
    {
        $sucursal ??= Sucursal::create([
            'nombre' => 'Casa Matriz', 'codigo' => 0, 'direccion' => 'Av Test 123',
            'municipio' => 'Santa Cruz', 'activa' => true,
        ]);
        $pos ??= PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja Central',
            'tipo_punto_venta' => 'Fijo', 'codigo' => 0,
            'cuis' => 'CUIS-TEST', 'cuis_vigencia' => now()->addMonths(6),
            'cufd' => 'CUFD-TEST', 'cufd_vigencia' => now()->addDay(),
            'codigo_control' => 'CTRL-TEST-123',
            'activo' => true,
        ]);
        $cliente = Cliente::create(['nombre' => 'CLIENTE TEST SA', 'nit' => '1020304050']);
        $producto = Producto::create([
            'codigo' => 'TST-'.uniqid(), 'descripcion' => 'Producto test',
            'costo' => 10, 'precio' => 100, 'stock' => 50,
        ]);
        $venta = Venta::create([
            'numero' => 'VTA-'.uniqid(), 'tipo' => 'con_factura', 'modalidad' => 'contado',
            'fecha' => now()->toDateString(), 'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre, 'nit_cliente' => $cliente->nit,
            'subtotal' => 100, 'descuento' => 0, 'total' => 100,
            'base_df' => 100, 'debito_fiscal' => 13, 'estado' => 'activa',
            'sucursal_id' => $sucursal->id, 'punto_venta_id' => $pos->id,
            'codigo_sucursal' => (int) $sucursal->codigo, 'codigo_punto_venta' => (int) $pos->codigo,
        ]);
        DetalleVenta::create([
            'venta_id' => $venta->id, 'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo, 'descripcion_producto' => $producto->descripcion,
            'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100,
        ]);

        return $venta;
    }

    public function test_emision_computarizada_por_defecto_sin_firma(): void
    {
        $this->assertSame('computarizada', SiatConfig::get('siat_modalidad'));

        $factura = app(FacturaService::class)->emitirDesdeVenta($this->armarVentaFacturable(), $this->admin->id);

        $this->assertSame('emitida', $factura->estado);
        $this->assertStringContainsString('facturaComputarizadaCompraVenta', $factura->xml_firmado);
        $this->assertStringContainsString('xsi:noNamespaceSchemaLocation="facturaComputarizadaCompraVenta.xsd"', $factura->xml_firmado);
        $this->assertStringNotContainsString('<Signature', $factura->xml_firmado);
        $this->assertStringNotContainsString('<codigoControl>', $factura->xml_firmado);
        $this->assertStringContainsString('<complemento xsi:nil="true"/>', $factura->xml_firmado);
        $this->assertStringContainsString('<numeroTarjeta xsi:nil="true"/>', $factura->xml_firmado);
        $this->assertStringContainsString('<montoGiftCard xsi:nil="true"/>', $factura->xml_firmado);
        $this->assertStringContainsString('<cafc xsi:nil="true"/>', $factura->xml_firmado);
        $this->assertStringContainsString('<numeroSerie xsi:nil="true"/>', $factura->xml_firmado);
        $this->assertStringContainsString('<numeroImei xsi:nil="true"/>', $factura->xml_firmado);
    }

    public function test_emision_electronica_simulador_incluye_marcador_firma(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');

        $factura = app(FacturaService::class)->emitirDesdeVenta($this->armarVentaFacturable(), $this->admin->id);

        $this->assertSame('emitida', $factura->estado);
        $this->assertStringContainsString('facturaElectronicaCompraVenta', $factura->xml_firmado);
        $this->assertStringContainsString('xsi:noNamespaceSchemaLocation="facturaElectronicaCompraVenta.xsd"', $factura->xml_firmado);
        $this->assertStringContainsString('<firmaDigital>', $factura->xml_firmado);
        $this->assertStringNotContainsString('<codigoControl>', $factura->xml_firmado);
    }

    public function test_nit_de_nueve_digitos_se_envia_como_tipo_nit(): void
    {
        $venta = $this->armarVentaFacturable();
        $venta->cliente->update([
            'nit' => '672047026',
            'codigo_tipo_documento' => 5,
        ]);
        $venta->update(['nit_cliente' => '672047026']);

        $factura = app(FacturaService::class)->emitirDesdeVenta($venta->fresh(), $this->admin->id);

        $this->assertStringContainsString('<codigoTipoDocumentoIdentidad>5</codigoTipoDocumentoIdentidad>', $factura->xml_firmado);
        $this->assertStringContainsString('<numeroDocumento>672047026</numeroDocumento>', $factura->xml_firmado);
    }

    // ------------------------------------------------------------ Vigencia CUFD

    public function test_emitir_sin_cufd_se_bloquea(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Suc', 'codigo' => 3, 'activa' => true,
        ]);
        $pos = PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja',
            'tipo_punto_venta' => 'Fijo', 'codigo' => 1,
            'cuis' => 'CUIS-X', 'cuis_vigencia' => now()->addMonths(6),
            'activo' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/CUFD vigente/');
        app(FacturaService::class)->emitirDesdeVenta($this->armarVentaFacturable($pos, $sucursal), $this->admin->id);
    }

    public function test_emitir_con_cufd_vencido_se_bloquea(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Suc', 'codigo' => 3, 'activa' => true]);
        $pos = PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja',
            'tipo_punto_venta' => 'Fijo', 'codigo' => 1,
            'cuis' => 'CUIS-X', 'cuis_vigencia' => now()->addMonths(6),
            'cufd' => 'CUFD-VIEJO', 'cufd_vigencia' => now()->subHour(),
            'activo' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(FacturaService::class)->emitirDesdeVenta($this->armarVentaFacturable($pos, $sucursal), $this->admin->id);
    }

    // ------------------------------------------------------------ Guardarraíl + cert + comando

    public function test_no_se_puede_guardar_simulador_con_produccion(): void
    {
        $response = $this->actingAs($this->admin)->post(route('configuracion.siat'), [
            'siat_modo' => 'simulador',
            'siat_ambiente' => 'produccion',
            'siat_modalidad' => 'computarizada',
            'siat_nit' => '1020304050',
            'siat_razon_social' => 'EMPRESA TEST SRL',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(Configuracion::where('clave', 'siat_ambiente')->first());
    }

    public function test_certificado_se_resuelve_en_disco_local(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('siat/test-cert.p12', 'contenido-falso');
        Configuracion::set('siat_certificado_path', 'siat/test-cert.p12', 'file');
        Configuracion::set('siat_modalidad', 'electronica', 'text');
        Configuracion::set('siat_modo', 'real', 'text');

        try {
            (new SiatService)->firmarXml('<?xml version="1.0"?><facturaElectronicaCompraVenta/>');
            $this->fail('Debía fallar al leer un .p12 inválido.');
        } catch (\RuntimeException $e) {
            // Lo encontró en disco local e intentó leerlo (no es error de ruta).
            $this->assertStringContainsString('No se pudo leer el .p12', $e->getMessage());
        }
    }

    public function test_comando_renueva_cufd(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Suc', 'codigo' => 3, 'activa' => true]);
        $pos = PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja',
            'tipo_punto_venta' => 'Fijo', 'codigo' => 1,
            'cuis' => 'CUIS-CRON', 'cuis_vigencia' => now()->addMonths(6),
            'activo' => true,
        ]);

        $this->artisan('siat:renovar-cufd')->assertSuccessful();
        $this->assertNotNull($pos->fresh()->cufd);
        $this->assertTrue($pos->fresh()->tieneCufdVigente());
    }
}
