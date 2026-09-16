<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\EtapasCrmSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesPermisosSeeder::class,
            EtapasCrmSeeder::class,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['rol' => 'admin', 'activo' => true]);
    }

    protected function leadEn(EtapaCrm $etapa, array $attrs = []): Lead
    {
        return Lead::factory()->for($etapa, 'etapa')->create([
            'nombre' => 'Cliente Ganado SA',
            'telefono' => '59171234567',
            'telefono_normalizado' => '59171234567',
            'correo' => 'compras@ganado.bo',
            'ciudad' => 'Santa Cruz',
            ...$attrs,
        ]);
    }

    public function test_convertir_crea_cliente_y_redirige_a_venta(): void
    {
        $compro = EtapaCrm::where('slug', 'compro')->firstOrFail();
        $lead = $this->leadEn($compro);

        $response = $this->actingAs($this->admin())->get(route('crm.leads.convertir', $lead));

        $cliente = Cliente::where('telefono', '59171234567')->firstOrFail();
        $this->assertSame('Cliente Ganado SA', $cliente->nombre);
        $this->assertSame($cliente->id, $lead->fresh()->cliente_id);
        $response->assertRedirect(route('ventas.create', [
            'cliente_id' => $cliente->id,
            'lead_id' => $lead->id,
        ]));
    }

    public function test_convertir_reutiliza_cliente_existente(): void
    {
        $compro = EtapaCrm::where('slug', 'compro')->firstOrFail();
        $existente = Cliente::create(['nombre' => 'cliente ganado sa', 'telefono' => '70000000']);
        $lead = $this->leadEn($compro);

        $this->actingAs($this->admin())->get(route('crm.leads.convertir', $lead));

        $this->assertSame(1, Cliente::count());
        $this->assertSame($existente->id, $lead->fresh()->cliente_id);
    }

    public function test_convertir_fuera_de_compro_se_bloquea(): void
    {
        $lead = $this->leadEn(EtapaCrm::inicial());

        $this->actingAs($this->admin())
            ->get(route('crm.leads.convertir', $lead))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Cliente::count());
        $this->assertNull($lead->fresh()->cliente_id);
    }

    public function test_convertir_sin_permiso_ventas_es_403(): void
    {
        // Gerencia ve el CRM pero no opera ventas.
        $gerencia = User::factory()->create(['rol' => 'gerencia', 'activo' => true]);
        $compro = EtapaCrm::where('slug', 'compro')->firstOrFail();
        $lead = $this->leadEn($compro);

        $this->actingAs($gerencia)
            ->get(route('crm.leads.convertir', $lead))
            ->assertForbidden();
    }

    public function test_venta_desde_lead_vincula_cliente_al_lead(): void
    {
        $compro = EtapaCrm::where('slug', 'compro')->firstOrFail();
        $lead = $this->leadEn($compro);
        $this->actingAs($this->admin())->get(route('crm.leads.convertir', $lead));
        $cliente = Cliente::firstOrFail();

        $producto = Producto::create([
            'codigo' => 'CV-1', 'descripcion' => 'Prod',
            'costo' => 10, 'precio' => 20, 'stock' => 50,
        ]);
        $this->actingAs($this->admin())->post(route('ventas.store'), [
            'cliente_id' => $cliente->id,
            'tipo' => 'sin_factura',
            'modalidad' => 'contado',
            'fecha' => now()->toDateString(),
            'lead_id' => $lead->id,
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 20],
            ],
        ])->assertSessionHas('exito');

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame($lead->id, $venta->lead_id);
        $this->assertSame($cliente->id, $lead->fresh()->cliente_id);
    }

    public function test_create_prefill_muestra_lead_y_cliente(): void
    {
        $cliente = Cliente::create(['nombre' => 'Prefill SA', 'telefono' => '70000001']);
        $lead = $this->leadEn(EtapaCrm::inicial(), ['cliente_id' => $cliente->id]);

        $this->actingAs($this->admin())
            ->get(route('ventas.create', ['cliente_id' => $cliente->id, 'lead_id' => $lead->id]))
            ->assertOk()
            ->assertSee('Venta desde el lead', false)
            ->assertSee('name="lead_id" value="'.$lead->id.'"', false);
    }
}
