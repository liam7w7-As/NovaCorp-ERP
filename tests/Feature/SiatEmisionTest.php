<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\DetalleVenta;
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
        $this->assertSame(9, SiatService::modulo11('1'));
        $this->assertSame(0, SiatService::modulo11('0'));
        $this->assertSame('FF', SiatService::decimalAHex('255'));
        $this->assertSame('10', SiatService::decimalAHex('16'));
    }

    public function test_generar_cuf_real_aplica_anchos_fijos(): void
    {
        Configuracion::set('siat_modo', 'real', 'text');
        $siat = new SiatService;
        $args = ['1020304050', '20260115120000000', '5', 2, 1, 1, 1, '7', '2'];

        $corto = $siat->generarCuf(...$args);
        $largo = $siat->generarCuf('1020304050', '20260115120000000', '0005', 2, 1, 1, '01', '0000000007', '0002');

        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $corto);
        $this->assertSame($largo, $corto, 'Sucursal, sector, número y PV deben ir con ceros a la izquierda.');
        $this->assertSame($corto, $siat->generarCuf(...$args), 'Mismos insumos → mismo CUF (determinista).');
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
        $this->assertStringContainsString('<facturaComputarizadaCompraVenta>', $factura->xml_firmado);
        $this->assertStringNotContainsString('<Signature', $factura->xml_firmado);
        $this->assertStringNotContainsString('<firmaDigital>', $factura->xml_firmado);
    }

    public function test_emision_electronica_simulador_incluye_marcador_firma(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');

        $factura = app(FacturaService::class)->emitirDesdeVenta($this->armarVentaFacturable(), $this->admin->id);

        $this->assertSame('emitida', $factura->estado);
        $this->assertStringContainsString('<facturaElectronicaCompraVenta>', $factura->xml_firmado);
        $this->assertStringContainsString('<firmaDigital>', $factura->xml_firmado);
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
