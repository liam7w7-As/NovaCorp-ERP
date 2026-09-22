<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\SucursalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CufdSupervisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin CUFD',
            'email' => 'cufd@giseca.test',
            'password' => bcrypt('secret123'),
            'rol' => 'admin',
            'activo' => true,
        ]);
        $this->sucursal = Sucursal::create([
            'codigo' => 0,
            'nombre' => 'Casa Matriz',
            'municipio' => 'Santa Cruz',
            'activa' => true,
        ]);
    }

    public function test_comando_omite_cufd_con_mas_de_una_hora_de_vigencia(): void
    {
        $puntoVenta = $this->crearPuntoVenta([
            'cufd' => 'CUFD-AUN-VIGENTE',
            'codigo_control' => 'CTRL-VIGENTE',
            'cufd_vigencia' => now()->addHours(3),
        ]);

        $this->artisan('siat:renovar-cufd')->assertSuccessful();

        $this->assertSame('CUFD-AUN-VIGENTE', $puntoVenta->fresh()->cufd);
    }

    public function test_comando_renueva_cufd_cuando_falta_menos_de_una_hora(): void
    {
        $puntoVenta = $this->crearPuntoVenta([
            'cufd' => 'CUFD-POR-VENCER',
            'codigo_control' => 'CTRL-ANTERIOR',
            'cufd_vigencia' => now()->addMinutes(30),
        ]);

        $this->artisan('siat:renovar-cufd')->assertSuccessful();

        $puntoVenta->refresh();
        $this->assertNotSame('CUFD-POR-VENCER', $puntoVenta->cufd);
        $this->assertTrue($puntoVenta->tieneCufdVigente());
    }

    public function test_layout_advierte_cuando_cufd_esta_proximo_a_vencer(): void
    {
        Configuracion::set('siat_modo', 'real', 'text');
        $puntoVenta = $this->crearPuntoVenta([
            'cufd' => 'CUFD-POR-VENCER',
            'codigo_control' => 'CTRL-VIGENTE',
            'cufd_vigencia' => now()->addMinutes(90),
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession([
                SucursalContext::SESSION_SUCURSAL_KEY => $this->sucursal->id,
                SucursalContext::SESSION_POS_KEY => $puntoVenta->id,
            ])
            ->get(route('productos.index'));

        $response->assertOk()
            ->assertSee('renovación automática se ejecutará antes del vencimiento')
            ->assertSee('Revisar CUFD');
    }

    /** @param array<string, mixed> $atributos */
    private function crearPuntoVenta(array $atributos = []): PuntoVenta
    {
        return PuntoVenta::create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'codigo' => 0,
            'nombre' => 'Caja Central',
            'tipo_punto_venta' => 'Punto de Venta Fijo',
            'cuis' => 'CUIS-PRUEBA',
            'cuis_vigencia' => now()->addMonths(6),
            'activo' => true,
        ], $atributos));
    }
}
