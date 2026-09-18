<?php

namespace Tests\Feature;

use App\Http\Controllers\CrmController;
use App\Models\CanalWhatsapp;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use Database\Seeders\CrmDemoSeeder;
use Database\Seeders\EtapasCrmSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFoundationTest extends TestCase
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

    public function test_it_seeds_the_nine_conversation_funnel_stages(): void
    {
        $this->assertSame(9, EtapaCrm::where('activa', true)->count());
        $this->assertSame(
            ['menu', 'informacion', 'precio', 'por-atender', 'atendido', 'interesado', 'por-pagar', 'compro', 'perdido'],
            EtapaCrm::where('activa', true)->orderBy('orden')->pluck('slug')->all()
        );
    }

    public function test_an_administrator_can_create_a_channel_and_assign_a_seller(): void
    {
        $admin = $this->usuario('admin');
        $vendedor = $this->usuario('vendedor');

        $this->actingAs($admin)
            ->get(route('crm.canales'))
            ->assertOk()
            ->assertSee('Webhook Meta')
            ->assertSee(route('meta.whatsapp.webhook.receive'));

        $response = $this->actingAs($admin)->post(route('crm.canales.store'), [
            'nombre' => 'Ventas La Paz',
            'ciudad' => 'La Paz',
            'telefono' => '+591 71234567',
            'activo' => '1',
            'vendedores' => [$vendedor->id],
        ]);

        $response->assertRedirect(route('crm.canales'));
        $this->assertDatabaseHas('canal_whatsapps', [
            'nombre' => 'Ventas La Paz',
            'telefono_normalizado' => '59171234567',
        ]);

        $canal = CanalWhatsapp::where('nombre', 'Ventas La Paz')->firstOrFail();
        $this->assertTrue($canal->vendedores()->whereKey($vendedor->id)->exists());
    }

    public function test_an_administrator_can_review_the_meta_readiness_diagnostic(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'services.meta_whatsapp.access_token' => null,
            'services.meta_whatsapp.app_secret' => 'secret-test',
            'services.meta_whatsapp.webhook_verify_token' => 'verify-test',
        ]);

        $admin = $this->usuario('admin');
        $vendedor = $this->usuario('vendedor');
        $canal = CanalWhatsapp::factory()->create([
            'nombre' => 'Ventas Santa Cruz',
            'ciudad' => 'Santa Cruz',
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-linea',
            'estado' => 'conectado',
            'meta_verified_name' => 'GISECA SRL',
            'meta_quality_rating' => 'GREEN',
            'meta_verificado_at' => now(),
        ]);
        $canal->vendedores()->attach($vendedor);
        Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create();

        $this->actingAs($admin)
            ->get(route('crm.diagnostico'))
            ->assertOk()
            ->assertSee('Diagnóstico Meta')
            ->assertSee('Dominio local detectado')
            ->assertSee(route('meta.whatsapp.webhook.receive'))
            ->assertSee('Ventas Santa Cruz')
            ->assertSee('GISECA SRL')
            ->assertSee('GREEN')
            ->assertSee('Listo');
    }

    public function test_a_seller_only_sees_owned_and_unassigned_leads_from_assigned_channels(): void
    {
        $vendedor = $this->usuario('vendedor');
        $otroVendedor = $this->usuario('vendedor');
        $canalAsignado = CanalWhatsapp::factory()->create();
        $otroCanal = CanalWhatsapp::factory()->create();
        $canalAsignado->vendedores()->attach($vendedor);
        $etapa = EtapaCrm::inicial();

        Lead::factory()->for($etapa, 'etapa')->for($canalAsignado, 'canalWhatsapp')->create([
            'vendedor_id' => $vendedor->id,
            'nombre' => 'Lead propio visible',
        ]);
        Lead::factory()->for($etapa, 'etapa')->for($canalAsignado, 'canalWhatsapp')->create([
            'vendedor_id' => null,
            'nombre' => 'Lead sin asignar visible',
        ]);
        Lead::factory()->for($etapa, 'etapa')->for($canalAsignado, 'canalWhatsapp')->create([
            'vendedor_id' => $otroVendedor->id,
            'nombre' => 'Lead de otro vendedor',
        ]);
        Lead::factory()->for($etapa, 'etapa')->for($otroCanal, 'canalWhatsapp')->create([
            'vendedor_id' => null,
            'nombre' => 'Lead de otra línea',
        ]);

        $response = $this->actingAs($vendedor)->get(route('crm.index'));

        $response->assertOk()
            ->assertSee('Lead propio visible')
            ->assertSee('Lead sin asignar visible')
            ->assertDontSee('Lead de otro vendedor')
            ->assertDontSee('Lead de otra línea');
    }

    public function test_a_seller_can_create_a_lead_only_on_an_assigned_channel(): void
    {
        $vendedor = $this->usuario('vendedor');
        $otroVendedor = $this->usuario('vendedor');
        $canal = CanalWhatsapp::factory()->create();
        $canal->vendedores()->attach($vendedor);

        $response = $this->actingAs($vendedor)->post(route('crm.leads.store'), [
            'nombre' => 'Consulta mostrador',
            'telefono' => '71234567',
            'canal_whatsapp_id' => $canal->id,
            'vendedor_id' => $otroVendedor->id,
            'tipo_consulta' => 'precio',
        ]);

        $response->assertRedirect(route('crm.index'));
        $this->assertDatabaseHas('leads', [
            'nombre' => 'Consulta mostrador',
            'telefono_normalizado' => '59171234567',
            'vendedor_id' => $vendedor->id,
            'canal_whatsapp_id' => $canal->id,
        ]);

        $canalNoAsignado = CanalWhatsapp::factory()->create();
        $this->actingAs($vendedor)->post(route('crm.leads.store'), [
            'telefono' => '72345678',
            'canal_whatsapp_id' => $canalNoAsignado->id,
        ])->assertNotFound();
    }

    public function test_a_seller_cannot_manage_commercial_channels(): void
    {
        $vendedor = $this->usuario('vendedor');

        $this->actingAs($vendedor)->get(route('crm.canales'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('crm.diagnostico'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('crm.canales.store'), [
            'nombre' => 'No permitida',
            'telefono' => '70000001',
        ])->assertForbidden();
    }

    public function test_closing_a_lead_records_the_closing_time(): void
    {
        $admin = $this->usuario('admin');
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create();
        $ganada = EtapaCrm::where('slug', 'compro')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('crm.leads.update', $lead), [
            'nombre' => $lead->nombre,
            'telefono' => $lead->telefono,
            'etapa_crm_id' => $ganada->id,
            'canal_whatsapp_id' => $lead->canal_whatsapp_id,
            'vendedor_id' => $lead->vendedor_id,
            'valor_estimado' => $lead->valor_estimado,
        ]);

        $response->assertRedirect();
        $this->assertSame($ganada->id, $lead->fresh()->etapa_crm_id);
        $this->assertNotNull($lead->fresh()->cerrado_at);
    }

    public function test_a_seller_can_open_a_conversation_and_drag_an_accessible_lead(): void
    {
        $vendedor = $this->usuario('vendedor');
        $canal = CanalWhatsapp::factory()->create();
        $canal->vendedores()->attach($vendedor);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create([
            'vendedor_id' => $vendedor->id,
        ]);
        MensajeWhatsapp::factory()->for($lead)->create([
            'contenido' => 'Necesito una cotización.',
        ]);
        $destino = EtapaCrm::where('slug', 'informacion')->firstOrFail();

        $this->actingAs($vendedor)
            ->getJson(route('crm.leads.show', $lead))
            ->assertOk()
            ->assertJsonPath('lead.id', $lead->id)
            ->assertJsonPath('mensajes.0.contenido', 'Necesito una cotización.');

        $this->actingAs($vendedor)
            ->patchJson(route('crm.leads.etapa', $lead), ['etapa_crm_id' => $destino->id])
            ->assertOk()
            ->assertJsonPath('etapa.id', $destino->id);

        $this->assertSame($destino->id, $lead->fresh()->etapa_crm_id);
    }

    public function test_funnel_marks_unanswered_and_overdue_conversations(): void
    {
        $admin = $this->usuario('admin');
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create([
            'nombre' => 'Lead urgente WhatsApp',
            'ultima_interaccion_at' => now()->subHours(5),
        ]);
        MensajeWhatsapp::factory()->for($lead)->create([
            'direccion' => 'entrante',
            'contenido' => '¿Me responden la cotización?',
            'ocurrio_at' => now()->subHours(5),
        ]);
        $respondido = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create([
            'nombre' => 'Lead ya respondido',
        ]);
        MensajeWhatsapp::factory()->for($respondido)->create([
            'direccion' => 'saliente',
            'contenido' => 'Cotización enviada.',
            'ocurrio_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('crm.index'))
            ->assertOk()
            ->assertSee('Sin responder')
            ->assertSee('Más de 4h')
            ->assertSee('Errores')
            ->assertSee('Lead urgente WhatsApp')
            ->assertSee('¿Me responden la cotización?')
            ->assertSee('+4h')
            ->assertSee('5h sin respuesta')
            ->assertDontSee('Lead ya respondido" class="crm-badge-respuesta', false);

        $this->actingAs($admin)
            ->get(route('crm.index', ['seguimiento' => 'vencidos']))
            ->assertOk()
            ->assertSee('Lead urgente WhatsApp')
            ->assertDontSee('Lead ya respondido');
    }

    public function test_follow_up_filter_finds_old_unanswered_leads_before_board_limit(): void
    {
        $admin = $this->usuario('admin');
        $canal = CanalWhatsapp::factory()->create();
        $etapa = EtapaCrm::inicial();
        $leadAntiguo = Lead::factory()->for($etapa, 'etapa')->for($canal, 'canalWhatsapp')->create([
            'vendedor_id' => $admin->id,
            'nombre' => 'Lead antiguo sin responder',
            'etapa_actualizada_at' => now()->subDays(30),
            'ultima_interaccion_at' => now()->subDays(30),
        ]);
        MensajeWhatsapp::factory()->for($leadAntiguo)->create([
            'direccion' => 'entrante',
            'contenido' => 'Sigo esperando respuesta',
            'ocurrio_at' => now()->subDays(30),
        ]);

        Lead::factory()
            ->count(CrmController::MAX_LEADS_TABLERO + 1)
            ->for($etapa, 'etapa')
            ->for($canal, 'canalWhatsapp')
            ->create([
                'vendedor_id' => $admin->id,
                'etapa_actualizada_at' => now(),
                'ultima_interaccion_at' => now(),
            ]);

        $this->actingAs($admin)
            ->get(route('crm.index', ['seguimiento' => 'sin_responder']))
            ->assertOk()
            ->assertSee('Lead antiguo sin responder')
            ->assertSee('Sigo esperando respuesta');
    }

    public function test_a_seller_cannot_drag_another_sellers_lead(): void
    {
        $vendedor = $this->usuario('vendedor');
        $otroVendedor = $this->usuario('vendedor');
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create([
            'vendedor_id' => $otroVendedor->id,
        ]);
        $destino = EtapaCrm::where('slug', 'informacion')->firstOrFail();

        $this->actingAs($vendedor)
            ->patchJson(route('crm.leads.etapa', $lead), ['etapa_crm_id' => $destino->id])
            ->assertNotFound();

        $this->assertNotSame($destino->id, $lead->fresh()->etapa_crm_id);
    }

    public function test_demo_seeder_is_idempotent_and_populates_every_stage(): void
    {
        $this->seed(CrmDemoSeeder::class);
        $this->seed(CrmDemoSeeder::class);

        $this->assertSame(3, CanalWhatsapp::where('nombre', 'like', 'Demo %')->count());
        $this->assertSame(4, User::where('email', 'like', 'crm.%@giseca.demo')->count());
        $this->assertSame(18, Lead::where('origen', 'demo')->count());
        $this->assertSame(
            9,
            Lead::where('origen', 'demo')->distinct('etapa_crm_id')->count('etapa_crm_id')
        );
        $this->assertGreaterThanOrEqual(18, MensajeWhatsapp::count());
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
