<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\FacturaService;
use App\Services\SucursalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultisucursalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $casaMatriz;

    protected PuntoVenta $posCaja1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );

        $this->casaMatriz = Sucursal::firstOrCreate(
            ['codigo' => 0],
            [
                'nombre' => 'Casa Matriz',
                'direccion' => 'Av. Principal #100',
                'telefono' => '33445566',
                'municipio' => 'Santa Cruz',
                'ciudad' => 'Santa Cruz',
                'es_casa_matriz' => true,
                'activa' => true,
            ]
        );

        $this->posCaja1 = PuntoVenta::firstOrCreate(
            ['sucursal_id' => $this->casaMatriz->id, 'codigo' => 0],
            [
                'nombre' => 'Caja Central 0',
                'tipo' => 'caja',
                'cuis' => 'CUIS-TEST-1234',
                'cuis_vigencia' => now()->addMonths(6),
                'cufd' => 'CUFD-TEST-5678',
                'cufd_codigo_control' => 'CTRL-5678',
                'cufd_vigencia' => now()->addDay(),
                'activo' => true,
            ]
        );
    }

    public function test_admin_puede_ver_listado_sucursales_y_pos(): void
    {
        $response = $this->actingAs($this->admin)->get(route('sucursales.index'));

        $response->assertStatus(200);
        $response->assertSee('Sucursales y Puntos de Venta (SIAT)');
        $response->assertSee('Casa Matriz');
        $response->assertSee('Caja Central 0');
    }

    public function test_admin_puede_crear_nueva_sucursal(): void
    {
        $response = $this->actingAs($this->admin)->post(route('sucursales.store'), [
            'nombre' => 'Sucursal Equipetrol',
            'codigo' => 1,
            'direccion' => 'Av. San Martín #500',
            'telefono' => '71122334',
            'municipio' => 'Santa Cruz',
            'ciudad' => 'Santa Cruz',
        ]);

        $response->assertSessionHas('exito');
        $this->assertDatabaseHas('sucursales', [
            'nombre' => 'Sucursal Equipetrol',
            'codigo' => 1,
        ]);
    }

    public function test_admin_puede_crear_punto_de_venta_en_sucursal(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal Montero',
            'codigo' => 2,
            'direccion' => 'Calle Comercio 45',
            'telefono' => '78899000',
            'municipio' => 'Montero',
            'ciudad' => 'Montero',
            'activa' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('sucursales.puntos-venta.store', $sucursal), [
            'nombre' => 'Caja Montero 1',
            'codigo' => 1,
            'tipo_punto_venta' => 'Punto de Venta Fijo',
        ]);

        $response->assertSessionHas('exito');
        $this->assertDatabaseHas('puntos_venta', [
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Montero 1',
            'codigo' => 1,
        ]);
    }

    public function test_solicitar_cuis_y_cufd_para_punto_de_venta(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal Plan 3000',
            'codigo' => 3,
            'direccion' => 'Av. Paurito #20',
            'municipio' => 'Santa Cruz',
            'activa' => true,
        ]);

        $pos = PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja P3000',
            'tipo_punto_venta' => 'Punto de Venta Fijo',
            'codigo' => 0,
            'activo' => true,
        ]);

        // Solicitar CUIS
        $resCuis = $this->actingAs($this->admin)->post(route('sucursales.puntos-venta.cuis', $pos));
        $resCuis->assertSessionHas('exito');
        $pos->refresh();
        $this->assertNotNull($pos->cuis);
        $this->assertTrue($pos->tieneCuisVigente());

        // Solicitar CUFD
        $resCufd = $this->actingAs($this->admin)->post(route('sucursales.puntos-venta.cufd', $pos));
        $resCufd->assertSessionHas('exito');
        $pos->refresh();
        $this->assertNotNull($pos->cufd);
        $this->assertTrue($pos->tieneCufdVigente());
    }

    public function test_cambio_de_contexto_activo_sucursal_y_pos(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal Norte',
            'codigo' => 4,
            'direccion' => 'Km 8 al Norte',
            'municipio' => 'Warnes',
            'activa' => true,
        ]);

        $pos = PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Warnes 1',
            'tipo_punto_venta' => 'Punto de Venta Fijo',
            'codigo' => 1,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('sucursales.cambiar-activa'), [
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $pos->id,
        ]);

        $response->assertSessionHas('exito');
        $response->assertSessionHas(SucursalContext::SESSION_SUCURSAL_KEY, $sucursal->id);
        $response->assertSessionHas(SucursalContext::SESSION_POS_KEY, $pos->id);
    }

    public function test_emision_de_factura_asocia_sucursal_y_punto_de_venta_activos(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Sucursal Equipetrol Norte',
            'codigo' => 5,
            'direccion' => 'Calle 8 Este #12',
            'municipio' => 'Santa Cruz',
            'activa' => true,
        ]);

        $pos = PuntoVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja VIP',
            'tipo_punto_venta' => 'Punto de Venta Fijo',
            'codigo' => 2,
            'cuis' => 'CUIS-TEST-EQUIPETROL',
            'cuis_vigencia' => now()->addMonths(6),
            'cufd' => 'CUFD-TEST-EQUIPETROL',
            'cufd_codigo_control' => 'CTRL-EQP',
            'cufd_vigencia' => now()->addDay(),
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'nombre' => 'CORPORACION MINERA SA',
            'nit' => '1020304050',
            'tipo_documento' => 'NIT',
            'correo' => 'compras@minera.bo',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'codigo' => 'PROD-001',
            'descripcion' => 'Filtro Hidráulico Industrial',
            'costo' => 150.00,
            'precio' => 250.00,
            'stock' => 50,
            'stock_min' => 5,
            'codigo_sin' => '32110',
            'unidad_sin' => '58',
        ]);

        $venta = Venta::create([
            'numero' => 'VTA-EQP-0001',
            'tipo' => 'con_factura',
            'modalidad' => 'contado',
            'fecha' => now(),
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'nit_cliente' => $cliente->nit,
            'subtotal' => 250.00,
            'descuento' => 0.00,
            'total' => 250.00,
            'debito_fiscal' => 32.50,
            'estado' => 'activa',
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $pos->id,
            'codigo_sucursal' => 5,
            'codigo_punto_venta' => 2,
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo,
            'descripcion_producto' => $producto->descripcion,
            'cantidad' => 1,
            'precio_unitario' => 250.00,
            'subtotal' => 250.00,
        ]);

        // Simular contexto activo de sucursal
        session([
            SucursalContext::SESSION_SUCURSAL_KEY => $sucursal->id,
            SucursalContext::SESSION_POS_KEY => $pos->id,
        ]);

        /** @var FacturaService $service */
        $service = app(FacturaService::class);
        $factura = $service->emitirDesdeVenta($venta, $this->admin->id);

        $this->assertNotNull($factura);
        $this->assertEquals(5, $factura->codigo_sucursal);
        $this->assertEquals(2, $factura->codigo_punto_venta);
        $this->assertEquals($sucursal->id, $factura->sucursal_id);
        $this->assertEquals($pos->id, $factura->punto_venta_id);
        $this->assertStringContainsString('FAC-S5-P2-', $factura->numero_factura);
        $this->assertStringContainsString('<codigoSucursal>5</codigoSucursal>', $factura->xml_firmado);
        $this->assertStringContainsString('<codigoPuntoVenta>2</codigoPuntoVenta>', $factura->xml_firmado);
        $this->assertStringContainsString('<codigoProductoSin>32110</codigoProductoSin>', $factura->xml_firmado);
    }
}
