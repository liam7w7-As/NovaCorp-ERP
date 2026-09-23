<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Configuracion;
use App\Models\DetalleCompra;
use App\Models\DetalleNotaEntrega;
use App\Models\DetalleVenta;
use App\Models\NotaEntrega;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\FacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RendimientoIntegridadTest extends TestCase
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

    protected function crearProducto(string $codigo, float $stock, float $costo): Producto
    {
        return Producto::create([
            'codigo' => $codigo, 'descripcion' => 'Prod '.$codigo,
            'costo' => $costo, 'precio' => $costo + 10, 'stock' => $stock,
        ]);
    }

    public function test_codigos_internos_unicos_y_automaticos(): void
    {
        $a = Producto::create([
            'codigo' => 'CI-1', 'descripcion' => 'Filtro Aceite',
            'costo' => 1, 'precio' => 2, 'stock' => 1,
        ]);
        $b = Producto::create([
            'codigo' => 'CI-2', 'descripcion' => 'Filtro Aire',
            'costo' => 1, 'precio' => 2, 'stock' => 1,
        ]);

        $this->assertNotEmpty($a->codigo_interno);
        $this->assertNotSame($a->codigo_interno, $b->codigo_interno);
    }

    public function test_kardex_index_muestra_total_valorizado(): void
    {
        $this->crearProducto('K-1', 10, 5); // 50
        $this->crearProducto('K-2', 3, 20); // 60

        $response = $this->actingAs($this->admin)->get(route('kardex.index'));

        $response->assertStatus(200);
        $response->assertSee('110', false);
    }

    public function test_kardex_show_con_filtro_y_saldo_inicial(): void
    {
        $producto = $this->crearProducto('K-3', 0, 10);

        $compra = Compra::create([
            'numero' => 'FC-K-1', 'tipo' => 'sin_factura', 'modalidad' => 'contado',
            'proveedor_nombre' => 'Prov', 'fecha' => now()->subDays(10)->toDateString(),
            'subtotal' => 100, 'total' => 100,
        ]);
        DetalleCompra::create([
            'compra_id' => $compra->id, 'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo, 'descripcion_producto' => 'x',
            'cantidad' => 10, 'precio_unitario' => 10, 'subtotal' => 100,
        ]);
        $venta = Venta::create([
            'numero' => 'NV-K-1', 'tipo' => 'sin_factura', 'modalidad' => 'contado',
            'fecha' => now()->toDateString(), 'cliente_nombre' => 'Cli',
            'subtotal' => 20, 'total' => 20, 'estado' => 'activa',
        ]);
        DetalleVenta::create([
            'venta_id' => $venta->id, 'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo, 'descripcion_producto' => 'x',
            'cantidad' => 4, 'cantidad_entregada' => 4, 'precio_unitario' => 20, 'subtotal' => 80,
        ]);
        $nota = NotaEntrega::create([
            'numero' => 'NE-K-1',
            'venta_id' => $venta->id,
            'fecha' => now()->toDateString(),
            'cliente_nombre' => 'Cli',
        ]);
        DetalleNotaEntrega::create([
            'nota_entrega_id' => $nota->id,
            'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo,
            'descripcion_producto' => 'x',
            'cantidad' => 4,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('kardex.show', $producto).'?desde='.now()->toDateString());

        $response->assertStatus(200);
        $response->assertSee('Saldo inicial', false);
        $response->assertSee('NE-K-1', false);
    }

    public function test_cuentas_muestra_saldo_contado_editado(): void
    {
        // Venta de contado con saldo (caso que antes quedaba invisible).
        $venta = Venta::create([
            'numero' => 'NV-C-1', 'tipo' => 'sin_factura', 'modalidad' => 'contado',
            'fecha' => now()->toDateString(), 'cliente_nombre' => 'Cli Contado',
            'subtotal' => 100, 'total' => 100, 'pagado' => 30, 'estado' => 'activa',
        ]);

        $response = $this->actingAs($this->admin)->get(route('cuentas.index'));

        $response->assertStatus(200);
        $response->assertSee('NV-C-1', false);
        $this->assertStringContainsString('70', $response->getContent());
    }

    public function test_reporte_anulaciones_descarga_csv(): void
    {
        Configuracion::set('siat_nit', '1020304050', 'text');
        $sucursal = Sucursal::create(['nombre' => 'M', 'codigo' => 0, 'activa' => true]);
        $pv = PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja', 'tipo_punto_venta' => 'Fijo',
            'codigo' => 0, 'cuis' => 'CUIS-R', 'cuis_vigencia' => now()->addMonths(6),
            'cufd' => 'CUFD-R', 'cufd_vigencia' => now()->addDay(), 'activo' => true,
        ]);
        $cliente = Cliente::create(['nombre' => 'CLI R', 'nit' => '1020304050']);
        $producto = $this->crearProducto('R-1', 50, 10);
        $venta = Venta::create([
            'numero' => 'VTA-R-1', 'tipo' => 'con_factura', 'modalidad' => 'contado',
            'fecha' => now()->toDateString(), 'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre, 'nit_cliente' => $cliente->nit,
            'subtotal' => 100, 'total' => 100, 'estado' => 'activa',
            'sucursal_id' => $sucursal->id, 'punto_venta_id' => $pv->id,
            'codigo_sucursal' => 0, 'codigo_punto_venta' => 0,
        ]);
        DetalleVenta::create([
            'venta_id' => $venta->id, 'producto_id' => $producto->id,
            'codigo_producto' => $producto->codigo, 'descripcion_producto' => 'x',
            'cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100,
        ]);

        $service = app(FacturaService::class);
        $factura = $service->emitirDesdeVenta($venta, $this->admin->id);
        $service->anular($factura, 1);

        $response = $this->actingAs($this->admin)->get(route('facturas.reporte'));

        $response->assertStatus(200);
        $this->assertStringContainsString($factura->numero_factura, $response->streamedContent());
    }

    public function test_agregar_pago_no_puede_superar_monto(): void
    {
        $this->actingAs($this->admin)->post(route('comprobantes.store'), [
            'tipo' => 'ingreso', 'entidad' => 'Ent', 'concepto' => 'Conc',
            'monto' => 100, 'fecha' => now()->toDateString(),
        ])->assertSessionHas('exito');
        $comp = Comprobante::latest('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('comprobantes.pagos.store', $comp), [
                'forma_pago' => 'tarjeta', 'monto' => 50,
            ])->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post(route('comprobantes.pagos.store', $comp), [
                'forma_pago' => 'efectivo', 'monto' => 100,
            ]);
        // El pago inicial ya cubre el total: ni siquiera el exacto duplica.
        $this->assertSame(1, $comp->pagos()->count());
    }

    public function test_kpis_ventas_respetan_filtros(): void
    {
        foreach ([['contado', 100], ['credito', 200]] as [$modalidad, $total]) {
            Venta::create([
                'numero' => 'VTA-KPI-'.$modalidad, 'tipo' => 'sin_factura', 'modalidad' => $modalidad,
                'fecha' => now()->toDateString(), 'cliente_nombre' => 'Cli',
                'subtotal' => $total, 'total' => $total, 'estado' => 'activa',
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('ventas.index', ['modalidad' => 'credito']));
        $response->assertOk();
        $kpis = $response->viewData('kpis');
        $this->assertEquals(200, (float) $kpis['total']);
        $this->assertSame(1, $kpis['documentos']);
    }
}
