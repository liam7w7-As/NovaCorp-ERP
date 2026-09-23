<?php

namespace Tests\Feature;

use App\Models\CanalWhatsapp;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use Database\Seeders\EtapasCrmSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmMejorasTest extends TestCase
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

    public function test_ventana_abierta_con_mensaje_reciente(): void
    {
        $admin = $this->admin();
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create();
        MensajeWhatsapp::factory()->for($lead)->create([
            'direccion' => 'entrante',
            'ocurrio_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin)
            ->getJson(route('crm.leads.show', $lead))
            ->assertOk()
            ->assertJsonPath('lead.ventana_abierta', true);
    }

    public function test_ventana_cerrada_con_mensaje_antiguo(): void
    {
        $admin = $this->admin();
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create();
        MensajeWhatsapp::factory()->for($lead)->create([
            'direccion' => 'entrante',
            'ocurrio_at' => now()->subHours(30),
        ]);

        $this->actingAs($admin)
            ->getJson(route('crm.leads.show', $lead))
            ->assertOk()
            ->assertJsonPath('lead.ventana_abierta', false)
            ->assertJsonPath('lead.motivo_ventana', 'Fuera de la ventana de 24h: Meta puede rechazar texto libre (usa respuesta a su último mensaje para reabrirla).');
    }

    public function test_archivo_no_soportado_es_rechazado(): void
    {
        $admin = $this->admin();
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-1',
            'access_token' => 'token-test',
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create();

        $this->actingAs($admin)
            ->postJson(route('crm.leads.mensajes.store', $lead), [
                'archivo' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');
    }

    public function test_webhook_reutiliza_lead_abierto_y_abre_otro_si_cerro(): void
    {
        $canal = CanalWhatsapp::factory()->create(['phone_number_id' => 'phone-123']);
        $ganada = EtapaCrm::where('slug', 'compro')->firstOrFail();

        // Lead abierto existente: segundo mensaje suma al mismo lead.
        $abierto = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create([
            'telefono' => '59171234567',
            'telefono_normalizado' => '59171234567',
        ]);
        $this->postJson('/api/webhooks/meta/whatsapp', $this->payload('wamid.dup.1', '59171234567'))
            ->assertOk()->assertJsonPath('mensajes', 1);
        $this->assertSame($abierto->id, MensajeWhatsapp::where('meta_message_id', 'wamid.dup.1')->firstOrFail()->lead_id);

        // Lead cerrado: el contacto nuevo abre otro lead.
        $abierto->forceFill(['cerrado_at' => now()])->save();
        $ganada->leads()->save($abierto);
        $this->postJson('/api/webhooks/meta/whatsapp', $this->payload('wamid.dup.2', '59171234567'))
            ->assertOk()->assertJsonPath('mensajes', 1);
        $nuevo = MensajeWhatsapp::where('meta_message_id', 'wamid.dup.2')->firstOrFail()->lead_id;
        $this->assertNotSame($abierto->id, $nuevo);
        $this->assertNull(Lead::findOrFail($nuevo)->cerrado_at);
    }

    public function test_envio_fallido_no_deja_huerfanos_en_storage(): void
    {
        Storage::fake('public');
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://graph.facebook.com/v21.0/phone-999/media') {
                return Http::response(['id' => 'media-out-9']);
            }

            return Http::response(['error' => ['message' => 'Rejected']], 400);
        });

        $admin = $this->admin();
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'graph_version' => 'v21.0',
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create();

        $this->actingAs($admin)
            ->postJson(route('crm.leads.mensajes.store', $lead), [
                'mensaje' => 'Cotización.',
                'archivo' => UploadedFile::fake()->create('cotizacion.pdf', 120, 'application/pdf'),
            ])
            ->assertUnprocessable();

        $this->assertCount(0, Storage::disk('public')->allFiles('whatsapp'));
        $this->assertSame(0, MensajeWhatsapp::where('lead_id', $lead->id)->count());
    }

    public function test_index_informa_limite_del_tablero(): void
    {
        $this->actingAs($this->admin())
            ->get(route('crm.index'))
            ->assertOk()
            ->assertViewHas('hayMas', false);
    }

    public function test_lead_se_elimina_y_aparece_en_papelera(): void
    {
        $admin = $this->admin();
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create(['nombre' => 'Borrable']);

        $this->actingAs($admin)
            ->deleteJson(route('crm.leads.destroy', $lead))
            ->assertOk()
            ->assertJsonPath('message', 'Lead enviado a papelera.');

        $this->assertTrue($lead->fresh()->trashed());

        $this->actingAs($admin)->get(route('papelera.index'))
            ->assertOk()
            ->assertSee('Borrable', false);
    }

    public function test_vendedor_no_elimina_lead_ajeno(): void
    {
        $vendedor = User::factory()->create(['rol' => 'vendedor', 'activo' => true]);
        $otro = User::factory()->create(['rol' => 'vendedor', 'activo' => true]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->create(['vendedor_id' => $otro->id]);

        $this->actingAs($vendedor)
            ->deleteJson(route('crm.leads.destroy', $lead))
            ->assertNotFound();
        $this->assertFalse($lead->fresh()->trashed());
    }

    public function test_plantilla_se_envia_por_meta(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.tpl.1']],
            ]),
        ]);

        $admin = $this->admin();
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'graph_version' => 'v21.0',
            'plantilla_nombre' => 'seguimiento_cotizacion',
            'plantilla_idioma' => 'es',
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create([
            'telefono' => '59171234567',
            'telefono_normalizado' => '59171234567',
        ]);

        $this->actingAs($admin)
            ->postJson(route('crm.leads.mensajes.store', $lead), [
                'mensaje' => 'Seguimos atentos.',
                'usar_plantilla' => true,
            ])
            ->assertOk()
            ->assertJsonPath('mensaje.tipo', 'plantilla');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v21.0/phone-999/messages'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'seguimiento_cotizacion'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'Seguimos atentos.';
        });
    }

    public function test_plantilla_sin_configurar_se_rechaza(): void
    {
        $admin = $this->admin();
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'plantilla_nombre' => null,
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create();

        $this->actingAs($admin)
            ->postJson(route('crm.leads.mensajes.store', $lead), [
                'mensaje' => 'Hola.',
                'usar_plantilla' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mensaje');
    }

    /** @return array<string, mixed> */
    private function payload(string $messageId, string $from): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '+59170000000',
                            'phone_number_id' => 'phone-123',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Cliente'],
                            'wa_id' => $from,
                        ]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Hola'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
