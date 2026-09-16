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

class MetaWhatsappIntegrationTest extends TestCase
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

    public function test_meta_can_verify_the_whatsapp_webhook(): void
    {
        config(['services.meta_whatsapp.webhook_verify_token' => 'giseca-verify']);

        $this->get('/api/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=giseca-verify&hub.challenge=ok-123')
            ->assertOk()
            ->assertSee('ok-123', false);

        $this->get('/api/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=bad&hub.challenge=ok-123')
            ->assertForbidden();
    }

    public function test_webhook_creates_an_inbound_lead_message_once(): void
    {
        $vendedor = $this->usuario('vendedor');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-123',
            'telefono' => '+591 70000000',
            'telefono_normalizado' => '59170000000',
        ]);
        $canal->vendedores()->attach($vendedor);
        $payload = $this->payloadMensajeEntrante('wamid.in.1');

        $this->postJson('/api/webhooks/meta/whatsapp', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'EVENT_RECEIVED')
            ->assertJsonPath('mensajes', 1);

        $this->postJson('/api/webhooks/meta/whatsapp', $payload)
            ->assertOk()
            ->assertJsonPath('mensajes', 0);

        $lead = Lead::where('telefono_normalizado', '59171234567')->firstOrFail();

        $this->assertSame(EtapaCrm::inicial()->id, $lead->etapa_crm_id);
        $this->assertSame($canal->id, $lead->canal_whatsapp_id);
        $this->assertSame($vendedor->id, $lead->vendedor_id);
        $this->assertSame(1, MensajeWhatsapp::where('meta_message_id', 'wamid.in.1')->count());
    }

    public function test_webhook_assigns_new_leads_by_channel_round_robin(): void
    {
        $primero = $this->usuario('vendedor');
        $segundo = $this->usuario('vendedor');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-123',
        ]);
        $canal->vendedores()->attach([$primero->id, $segundo->id]);

        $this->postJson('/api/webhooks/meta/whatsapp', $this->payloadMensajeEntrante('wamid.rr.1'))
            ->assertOk()
            ->assertJsonPath('mensajes', 1);

        $segundoPayload = $this->payloadMensajeEntrante('wamid.rr.2');
        $segundoPayload['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id'] = '59170000002';
        $segundoPayload['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] = 'Segundo Cliente';
        $segundoPayload['entry'][0]['changes'][0]['value']['messages'][0]['from'] = '59170000002';

        $this->postJson('/api/webhooks/meta/whatsapp', $segundoPayload)
            ->assertOk()
            ->assertJsonPath('mensajes', 1);

        $this->assertSame($primero->id, Lead::where('telefono_normalizado', '59171234567')->firstOrFail()->vendedor_id);
        $this->assertSame($segundo->id, Lead::where('telefono_normalizado', '59170000002')->firstOrFail()->vendedor_id);
        $this->assertSame($segundo->id, $canal->fresh()->ultimo_vendedor_asignado_id);
    }

    public function test_webhook_updates_message_statuses(): void
    {
        $mensaje = MensajeWhatsapp::factory()->create([
            'direccion' => 'saliente',
            'meta_message_id' => 'wamid.out.1',
            'estado' => 'enviado',
        ]);

        $this->postJson('/api/webhooks/meta/whatsapp', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'statuses' => [[
                            'id' => 'wamid.out.1',
                            'status' => 'delivered',
                            'timestamp' => '1799971400',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk()->assertJsonPath('statuses', 1);

        $this->assertSame('entregado', $mensaje->fresh()->estado);
    }

    public function test_webhook_downloads_inbound_media_files(): void
    {
        Storage::fake('public');
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'graph.facebook.com/v21.0/media-1')) {
                return Http::response([
                    'url' => 'https://lookaside.fbsbx.com/whatsapp/media-1',
                    'mime_type' => 'image/jpeg',
                    'file_size' => 10,
                    'id' => 'media-1',
                ]);
            }

            if ($request->url() === 'https://lookaside.fbsbx.com/whatsapp/media-1') {
                return Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(status: 404);
        });

        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-123',
            'access_token' => 'token-test',
        ]);
        $payload = $this->payloadMensajeEntrante('wamid.image.1');
        $payload['entry'][0]['changes'][0]['value']['messages'][0] = [
            'from' => '59171234567',
            'id' => 'wamid.image.1',
            'timestamp' => '1799971200',
            'type' => 'image',
            'image' => [
                'id' => 'media-1',
                'mime_type' => 'image/jpeg',
                'caption' => 'Foto de referencia',
            ],
        ];

        $this->postJson('/api/webhooks/meta/whatsapp', $payload)
            ->assertOk()
            ->assertJsonPath('mensajes', 1);

        $mensaje = MensajeWhatsapp::where('meta_message_id', 'wamid.image.1')->firstOrFail();

        $this->assertSame($canal->id, $mensaje->canal_whatsapp_id);
        $this->assertSame('imagen', $mensaje->tipo);
        $this->assertSame('image/jpeg', $mensaje->mime_type);
        $this->assertSame('media-1.jpg', $mensaje->nombre_archivo);
        $this->assertNotNull($mensaje->archivo_url);
        $this->assertCount(1, Storage::disk('public')->allFiles('whatsapp'));
    }

    public function test_crm_sends_text_messages_through_meta(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.sent.1']],
            ]),
        ]);

        $admin = $this->usuario('admin');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'graph_version' => 'v21.0',
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create([
            'telefono' => '59171234567',
            'telefono_normalizado' => '59171234567',
        ]);

        $this->actingAs($admin)
            ->postJson(route('crm.leads.mensajes.store', $lead), ['mensaje' => 'Buenas, te enviamos la cotización.'])
            ->assertOk()
            ->assertJsonPath('mensaje.contenido', 'Buenas, te enviamos la cotización.');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v21.0/phone-999/messages'
                && $request['to'] === '59171234567'
                && $request['text']['body'] === 'Buenas, te enviamos la cotización.'
                && $request->hasHeader('Authorization', 'Bearer token-test');
        });

        $this->assertDatabaseHas('mensajes_whatsapp', [
            'meta_message_id' => 'wamid.sent.1',
            'direccion' => 'saliente',
            'estado' => 'enviado',
        ]);
    }

    public function test_crm_sends_media_messages_through_meta(): void
    {
        Storage::fake('public');
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://graph.facebook.com/v21.0/phone-999/media') {
                return Http::response(['id' => 'media-out-1']);
            }

            if ($request->url() === 'https://graph.facebook.com/v21.0/phone-999/messages') {
                return Http::response([
                    'messaging_product' => 'whatsapp',
                    'messages' => [['id' => 'wamid.media.1']],
                ]);
            }

            return Http::response(status: 404);
        });

        $admin = $this->usuario('admin');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'graph_version' => 'v21.0',
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create([
            'telefono' => '59171234567',
            'telefono_normalizado' => '59171234567',
        ]);

        $this->actingAs($admin)
            ->post(route('crm.leads.mensajes.store', $lead), [
                'mensaje' => 'Adjuntamos la cotización solicitada.',
                'archivo' => UploadedFile::fake()->create('cotizacion.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('mensaje.tipo', 'documento')
            ->assertJsonPath('mensaje.contenido', 'Adjuntamos la cotización solicitada.')
            ->assertJsonPath('mensaje.meta_message_id', 'wamid.media.1');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v21.0/phone-999/media'
                && $request->hasHeader('Authorization', 'Bearer token-test');
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v21.0/phone-999/messages'
                && $request['to'] === '59171234567'
                && $request['type'] === 'document'
                && $request['document']['id'] === 'media-out-1'
                && $request['document']['caption'] === 'Adjuntamos la cotización solicitada.'
                && $request['document']['filename'] === 'cotizacion.pdf';
        });

        $mensaje = MensajeWhatsapp::where('meta_message_id', 'wamid.media.1')->firstOrFail();

        $this->assertSame('media-out-1', $mensaje->meta_media_id);
        $this->assertSame('application/pdf', $mensaje->mime_type);
        $this->assertSame('cotizacion.pdf', $mensaje->nombre_archivo);
        $this->assertNotNull($mensaje->archivo_url);
        $this->assertCount(1, Storage::disk('public')->allFiles('whatsapp'));
    }

    public function test_crm_retries_failed_text_messages_through_meta(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.retry.1']],
            ]),
        ]);

        $admin = $this->usuario('admin');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'graph_version' => 'v21.0',
        ]);
        $lead = Lead::factory()->for(EtapaCrm::inicial(), 'etapa')->for($canal, 'canalWhatsapp')->create([
            'telefono' => '59171234567',
            'telefono_normalizado' => '59171234567',
        ]);
        $fallido = MensajeWhatsapp::factory()->for($lead)->for($canal, 'canalWhatsapp')->for($admin, 'enviadoPor')->create([
            'direccion' => 'saliente',
            'contenido' => 'Mensaje anterior fallido.',
            'estado' => 'error',
            'meta_message_id' => 'wamid.failed.1',
        ]);

        $this->actingAs($admin)
            ->postJson(route('crm.leads.mensajes.reintentar', [$lead, $fallido]))
            ->assertOk()
            ->assertJsonPath('message', 'Mensaje reenviado.')
            ->assertJsonPath('mensaje.meta_message_id', 'wamid.retry.1')
            ->assertJsonPath('mensaje.estado_texto', 'Enviado')
            ->assertJsonPath('mensaje.puede_reintentar', false);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v21.0/phone-999/messages'
                && $request['to'] === '59171234567'
                && $request['text']['body'] === 'Mensaje anterior fallido.';
        });

        $this->assertSame('error', $fallido->fresh()->estado);
        $this->assertDatabaseHas('mensajes_whatsapp', [
            'meta_message_id' => 'wamid.retry.1',
            'direccion' => 'saliente',
            'estado' => 'enviado',
        ]);
    }

    public function test_admin_can_validate_a_whatsapp_channel_against_meta(): void
    {
        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), 'https://graph.facebook.com/v21.0/phone-999')) {
                return Http::response([
                    'id' => 'phone-999',
                    'display_phone_number' => '+591 70000000',
                    'verified_name' => 'GISECA SRL',
                    'quality_rating' => 'GREEN',
                ]);
            }

            return Http::response(status: 404);
        });

        $admin = $this->usuario('admin');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => 'phone-999',
            'access_token' => 'token-test',
            'graph_version' => 'v21.0',
            'estado' => 'pendiente',
            'ultimo_error' => 'Error anterior',
        ]);

        $this->actingAs($admin)
            ->postJson(route('crm.canales.probar-meta', $canal))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('canal.estado', 'conectado')
            ->assertJsonPath('canal.meta_verified_name', 'GISECA SRL')
            ->assertJsonPath('canal.meta_quality_rating', 'GREEN');

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://graph.facebook.com/v21.0/phone-999')
                && $request['fields'] === 'id,display_phone_number,verified_name,quality_rating'
                && $request->hasHeader('Authorization', 'Bearer token-test');
        });

        $canal->refresh();
        $this->assertSame('conectado', $canal->estado);
        $this->assertSame('GISECA SRL', $canal->meta_verified_name);
        $this->assertSame('+591 70000000', $canal->meta_display_phone_number);
        $this->assertSame('GREEN', $canal->meta_quality_rating);
        $this->assertNull($canal->ultimo_error);
        $this->assertNotNull($canal->meta_verificado_at);
    }

    public function test_channel_validation_requires_credentials(): void
    {
        config(['services.meta_whatsapp.access_token' => null]);

        $admin = $this->usuario('admin');
        $canal = CanalWhatsapp::factory()->create([
            'phone_number_id' => null,
            'access_token' => null,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->postJson(route('crm.canales.probar-meta', $canal))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('canal');

        $this->assertSame('error', $canal->fresh()->estado);
        $this->assertSame('Falta configurar token de acceso o Phone Number ID.', $canal->fresh()->ultimo_error);
    }

    /** @return array<string, mixed> */
    private function payloadMensajeEntrante(string $messageId): array
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
                            'profile' => ['name' => 'Marco Aguilera'],
                            'wa_id' => '59171234567',
                        ]],
                        'messages' => [[
                            'from' => '59171234567',
                            'id' => $messageId,
                            'timestamp' => '1799971200',
                            'type' => 'text',
                            'text' => ['body' => 'Buenas, necesito una cotización.'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
