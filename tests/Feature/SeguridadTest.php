<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguridadTest extends TestCase
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

    public function test_login_limitado_por_throttle(): void
    {
        $respuestas = [];
        for ($i = 0; $i < 6; $i++) {
            $respuestas[] = $this->post('/login', ['email' => 'nadie@giseca.com', 'password' => 'x']);
        }
        foreach (array_slice($respuestas, 0, 5) as $r) {
            $r->assertStatus(302);
        }
        end($respuestas)->assertStatus(429);
    }

    public function test_password_minimo_12_al_crear_usuario(): void
    {
        Rol::create(['clave' => 'vendedor', 'nombre' => 'Vendedor']);

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Corto', 'email' => 'corto@giseca.com',
            'password' => 'corta123', 'rol' => 'vendedor',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Largo', 'email' => 'largo@giseca.com',
            'password' => 'clave-segura-12', 'rol' => 'vendedor',
        ])->assertSessionHas('exito');
    }

    public function test_reset_requiere_password_correcta(): void
    {
        $producto = Producto::create([
            'codigo' => 'RST-1', 'descripcion' => 'No borrar',
            'costo' => 1, 'precio' => 2, 'stock' => 1,
        ]);

        $this->actingAs($this->admin)->post(route('configuracion.resetear'), [
            'confirmacion' => 'REINICIAR', 'password_actual' => 'incorrecta',
        ])->assertSessionHas('error');
        $this->assertNotNull($producto->fresh());

        $this->actingAs($this->admin)->post(route('configuracion.resetear'), [
            'confirmacion' => 'REINICIAR', 'password_actual' => 'secret123',
        ])->assertSessionHas('exito');
    }

    public function test_scope_sucursal_bloquea_escritura_ajena(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $sucA = Sucursal::create(['nombre' => 'A', 'codigo' => 1, 'activa' => true]);
        $sucB = Sucursal::create(['nombre' => 'B', 'codigo' => 2, 'activa' => true]);
        $vendedor = User::create([
            'name' => 'Vendedor A', 'email' => 'va@giseca.com',
            'password' => bcrypt('secret123'), 'rol' => 'vendedor',
            'activo' => true, 'sucursal_id' => $sucA->id,
        ]);
        $ventaB = Venta::create([
            'numero' => 'VTA-B-1', 'tipo' => 'sin_factura', 'modalidad' => 'contado',
            'fecha' => now()->toDateString(), 'cliente_nombre' => 'X',
            'subtotal' => 10, 'total' => 10, 'sucursal_id' => $sucB->id,
        ]);

        $this->actingAs($vendedor)->put(route('ventas.update', $ventaB), [])
            ->assertStatus(403);

        // Admin sí puede (falla validación, no permiso).
        $this->actingAs($this->admin)->put(route('ventas.update', $ventaB), [])
            ->assertStatus(302);
    }

    public function test_toggle_permiso_queda_auditado(): void
    {
        $this->seed(RolesPermisosSeeder::class);

        $this->actingAs($this->admin)
            ->post(route('permisos.toggle'), ['rol' => 'vendedor', 'habilidad' => 'ventas'])
            ->assertStatus(200);

        $this->assertDatabaseHas('auditorias', ['accion' => 'permiso', 'modelo' => 'Rol']);
        $this->assertStringContainsString(
            'ventas',
            Auditoria::where('accion', 'permiso')->latest('id')->firstOrFail()->descripcion
        );
    }

    public function test_export_csv_neutraliza_formulas(): void
    {
        Venta::create([
            'numero' => 'VTA-F-1', 'tipo' => 'sin_factura', 'modalidad' => 'contado',
            'fecha' => now()->format('Y-m-15'), 'cliente_nombre' => '=1+1',
            'subtotal' => 10, 'total' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.exportar', ['mes' => now()->format('Y-m')]));

        $response->assertStatus(200);
        $response->assertSee("'=1+1", false);
    }
}
