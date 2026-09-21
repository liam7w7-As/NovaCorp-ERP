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
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guardarraíles del modo REAL que no requieren red: identidad de
 * simulador, operaciones inexistentes y parseo de respuestas.
 */
class SiatPilotoGuardTest extends TestCase
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
        Configuracion::set('siat_modo', 'real', 'text');
    }

    protected function puntoSimulado(): PuntoVenta
    {
        $sucursal = Sucursal::create(['nombre' => 'M', 'codigo' => 0, 'activa' => true]);

        return PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja', 'tipo_punto_venta' => 'Fijo',
            'codigo' => 0, 'cuis' => 'SIM-CUIS-VIEJO', 'cuis_vigencia' => now()->addMonths(6),
            'cufd' => 'SIM-CUFD-VIEJO', 'cufd_vigencia' => now()->addDay(), 'activo' => true,
        ]);
    }

    public function test_cufd_real_con_cuis_simulado_se_bloquea_antes_de_red(): void
    {
        $pv = $this->puntoSimulado();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/simulador/');
        (new SiatService)->solicitarCufd(0, 0, $pv->cuis, $pv->id);
    }

    public function test_emitir_real_con_identidad_simulada_se_bloquea(): void
    {
        $pv = $this->puntoSimulado();
        $sucursal = $pv->sucursal;
        $cliente = Cliente::create(['nombre' => 'CLI', 'nit' => '1020304050']);
        $producto = Producto::create([
            'codigo' => 'G-1', 'descripcion' => 'Prod',
            'costo' => 10, 'precio' => 100, 'stock' => 50,
        ]);
        $venta = Venta::create([
            'numero' => 'VTA-G-1', 'tipo' => 'con_factura', 'modalidad' => 'contado',
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/simulador/');
        app(FacturaService::class)->emitirDesdeVenta($venta, $this->admin->id);
    }

    public function test_nota_real_se_bloquea_con_mensaje_claro(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sector 24/');
        (new SiatService)->recepcionNota('CUF-X', 'credito', 10, 'Motivo');
    }

    public function test_primer_mensaje_acepta_objeto_unico_y_arreglo(): void
    {
        $metodo = new \ReflectionMethod(SiatService::class, 'primerMensaje');

        $conArreglo = (object) ['mensajesList' => [(object) ['descripcion' => 'Uno']]];
        $this->assertSame('Uno', $metodo->invoke(null, $conArreglo));

        $conObjeto = (object) ['mensajesList' => (object) ['descripcion' => 'Unico']];
        $this->assertSame('Unico', $metodo->invoke(null, $conObjeto));

        $vacio = (object) [];
        $this->assertSame('', $metodo->invoke(null, $vacio));
    }

    public function test_normalizar_lista_acepta_arreglo_objeto_y_vacio(): void
    {
        $metodo = new \ReflectionMethod(SiatService::class, 'normalizarLista');

        $obj = (object) ['codigo' => '1'];
        $this->assertSame([$obj], $metodo->invoke(null, $obj));
        $this->assertSame([$obj], $metodo->invoke(null, [$obj]));
        $this->assertSame([], $metodo->invoke(null, null));
        $this->assertSame([], $metodo->invoke(null, []));
    }
}
