<?php

namespace Tests\Feature;

use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanzasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );
        $this->vendedor = User::firstOrCreate(
            ['email' => 'vendedor_test@giseca.com'],
            ['name' => 'Vendedor Test', 'password' => bcrypt('secret123'), 'rol' => 'vendedor', 'activo' => true]
        );
    }

    public function test_indice_finanzas_responde_200(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finanzas.index'));

        $response->assertStatus(200);
        $response->assertSee('Gestión de Finanzas');
    }

    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $response = $this->actingAs($this->vendedor)->get(route('finanzas.index'));

        $response->assertStatus(403);
    }

    public function test_store_rechaza_importe_cero_y_negativo(): void
    {
        foreach ([0, -5] as $importe) {
            $response = $this->actingAs($this->admin)->post(route('finanzas.store'), [
                'tipo' => 'gasto',
                'fecha' => now()->toDateString(),
                'concepto' => 'Importe inválido',
                'importe' => $importe,
            ]);

            $response->assertSessionHasErrors('importe');
        }

        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_store_crea_movimiento_valido(): void
    {
        $response = $this->actingAs($this->admin)->post(route('finanzas.store'), [
            'tipo' => 'ingreso',
            'fecha' => now()->toDateString(),
            'concepto' => 'Pago cliente',
            'detalle' => 'Detalle opcional',
            'importe' => 150.50,
        ]);

        $response->assertSessionHas('exito');
        $this->assertDatabaseHas('movimientos_caja', [
            'concepto' => 'Pago cliente',
            'tipo' => 'ingreso',
            'origen' => 'manual',
            'usuario_id' => $this->admin->id,
        ]);
    }
}
