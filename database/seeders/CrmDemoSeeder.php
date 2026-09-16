<?php

namespace Database\Seeders;

use App\Models\CanalWhatsapp;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use App\Services\Telefono;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CrmDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            EtapasCrmSeeder::class,
        ]);

        DB::transaction(function (): void {
            $vendedores = $this->crearVendedores();
            $canales = $this->crearCanales($vendedores);

            $this->crearLeads($vendedores, $canales);
        });

        $this->command?->info('CRM demo: 3 líneas, 3 vendedores, 1 gerente y 18 conversaciones listas.');
    }

    /** @return array<string, User> */
    private function crearVendedores(): array
    {
        $datos = [
            'lapaz' => ['name' => 'Demo Vendedor La Paz', 'email' => 'crm.lapaz@giseca.demo', 'rol' => 'vendedor'],
            'oriente' => ['name' => 'Demo Vendedor Oriente', 'email' => 'crm.oriente@giseca.demo', 'rol' => 'vendedor'],
            'central' => ['name' => 'Demo Vendedor Central', 'email' => 'crm.central@giseca.demo', 'rol' => 'vendedor'],
            'gerencia' => ['name' => 'Demo Gerencia CRM', 'email' => 'crm.gerencia@giseca.demo', 'rol' => 'gerencia'],
        ];

        $vendedores = [];
        foreach ($datos as $clave => $usuario) {
            $vendedores[$clave] = User::updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'password' => Hash::make('demo1234'),
                    'rol' => $usuario['rol'],
                    'activo' => true,
                ]
            );
        }

        return $vendedores;
    }

    /**
     * @param  array<string, User>  $vendedores
     * @return array<string, CanalWhatsapp>
     */
    private function crearCanales(array $vendedores): array
    {
        $datos = [
            'lapaz' => ['nombre' => 'Demo Ventas La Paz', 'ciudad' => 'La Paz', 'telefono' => '+591 72000001'],
            'santacruz' => ['nombre' => 'Demo Ventas Santa Cruz', 'ciudad' => 'Santa Cruz', 'telefono' => '+591 72000002'],
            'cochabamba' => ['nombre' => 'Demo Ventas Cochabamba', 'ciudad' => 'Cochabamba', 'telefono' => '+591 72000003'],
        ];

        $canales = CanalWhatsapp::withoutEvents(function () use ($datos): array {
            $resultado = [];

            foreach ($datos as $clave => $canal) {
                $resultado[$clave] = CanalWhatsapp::updateOrCreate(
                    ['telefono_normalizado' => Telefono::normalizar($canal['telefono'])],
                    [...$canal, 'estado' => 'pendiente', 'activo' => true]
                );
            }

            return $resultado;
        });

        $canales['lapaz']->vendedores()->sync([$vendedores['lapaz']->id, $vendedores['central']->id]);
        $canales['santacruz']->vendedores()->sync([$vendedores['oriente']->id, $vendedores['central']->id]);
        $canales['cochabamba']->vendedores()->sync([$vendedores['central']->id]);

        return $canales;
    }

    /**
     * @param  array<string, User>  $vendedores
     * @param  array<string, CanalWhatsapp>  $canales
     */
    private function crearLeads(array $vendedores, array $canales): void
    {
        $leads = [
            ['76010001', 'María Flores', 'Comercial Andina', 'La Paz', 'menu', 'lapaz', null, 'informacion', 1800, 'Entró por el menú principal. Pendiente de clasificación.'],
            ['76010002', 'Luis Mendoza', 'Repuestos Illimani', 'El Alto', 'menu', 'lapaz', 'lapaz', 'otro', 3200, 'Seleccionó una opción del menú de WhatsApp.'],
            ['76010015', 'Mónica Choque', 'Seguridad Andina', 'La Paz', 'informacion', 'lapaz', 'lapaz', 'informacion', 5200, 'Pidió disponibilidad y fotos. Pendiente de ampliar opciones.'],
            ['76010016', 'Valeria Montaño', 'Soluciones Altiplano', 'El Alto', 'informacion', 'lapaz', null, 'informacion', 3800, 'Busca información técnica antes de cotizar.'],
            ['76010017', 'Fernanda Salazar', 'Logística Bolivia', 'Santa Cruz', 'precio', 'santacruz', 'oriente', 'precio', 2600, 'Está comparando precio y tiempo de entrega.'],
            ['76010018', 'Juan Pérez', 'Taller Oriental', 'Santa Cruz', 'precio', 'santacruz', null, 'precio', 6100, 'Consultó precio por volumen.'],
            ['76010003', 'Carla Rojas', 'Constructora Horizonte', 'Santa Cruz', 'por-atender', 'santacruz', null, 'precio', 8500, 'Pendiente de primera respuesta comercial.'],
            ['76010004', 'Jorge Salvatierra', 'Servicios del Oriente', 'Santa Cruz', 'por-atender', 'santacruz', 'oriente', 'informacion', 2400, 'Requiere ficha técnica.'],
            ['76010005', 'Ana Quispe', 'Taller San Pedro', 'La Paz', 'atendido', 'lapaz', 'lapaz', 'otro', 1250, 'Primera llamada realizada.'],
            ['76010006', 'Diego Vargas', 'Distribuidora Central', 'Cochabamba', 'atendido', 'cochabamba', 'central', 'precio', 4700, 'Se envió una referencia de precios.'],
            ['76010007', 'Patricia Suárez', 'Ingeniería Atlas', 'Santa Cruz', 'interesado', 'santacruz', 'oriente', 'informacion', 12300, 'Interés confirmado, preparar propuesta.'],
            ['76010008', 'Marco López', 'Maquinaria Tunari', 'Cochabamba', 'interesado', 'cochabamba', 'central', 'precio', 6900, 'Solicitó cotización formal.'],
            ['76010009', 'Gabriela Nina', 'Ferretería Central', 'La Paz', 'por-pagar', 'lapaz', 'lapaz', 'precio', 5400, 'Cotización aceptada, pendiente de pago.'],
            ['76010010', 'Ramiro Peña', 'Obras del Valle', 'Cochabamba', 'por-pagar', 'cochabamba', 'central', 'otro', 9800, 'Esperando comprobante de transferencia.'],
            ['76010011', 'Sofía Castro', 'Proyectos del Sur', 'La Paz', 'compro', 'lapaz', 'lapaz', 'precio', 7600, 'Venta cerrada desde el CRM.'],
            ['76010012', 'Fernando Paz', 'Equipos Orientales', 'Santa Cruz', 'compro', 'santacruz', 'oriente', 'informacion', 15100, 'Cliente confirmado y derivado a ventas.'],
            ['76010013', 'Natalia Arce', 'Importadora Nova', 'Santa Cruz', 'perdido', 'santacruz', 'oriente', 'precio', 4300, 'Eligió otra propuesta.'],
            ['76010014', 'Óscar Molina', 'Metalúrgica Valle', 'Cochabamba', 'perdido', 'cochabamba', 'central', 'otro', 2750, 'Proyecto suspendido por el cliente.'],
        ];

        $etapas = EtapaCrm::query()->pluck('id', 'slug');

        Lead::withoutEvents(function () use ($leads, $etapas, $vendedores, $canales): void {
            foreach ($leads as $indice => $datos) {
                [$telefono, $nombre, $empresa, $ciudad, $etapa, $canal, $vendedor, $tipo, $valor, $notas] = $datos;
                $cerrado = in_array($etapa, ['compro', 'perdido'], true);

                $lead = Lead::updateOrCreate(
                    ['telefono_normalizado' => Telefono::normalizar($telefono), 'origen' => 'demo'],
                    [
                        'etapa_crm_id' => $etapas[$etapa],
                        'canal_whatsapp_id' => $canales[$canal]->id,
                        'vendedor_id' => $vendedor ? $vendedores[$vendedor]->id : null,
                        'nombre' => $nombre,
                        'telefono' => '+591 '.$telefono,
                        'empresa' => $empresa,
                        'ciudad' => $ciudad,
                        'tipo_consulta' => $tipo,
                        'valor_estimado' => $valor,
                        'notas' => $notas,
                        'ultima_interaccion_at' => now()->subHours($indice + 1),
                        'etapa_actualizada_at' => now()->subDays($indice % 5),
                        'cerrado_at' => $cerrado ? now()->subDays(1) : null,
                        'motivo_perdida' => $etapa === 'perdido' ? $notas : null,
                    ]
                );

                $this->crearMensajesDemo($lead, $vendedores, $indice);
            }
        });
    }

    /** @param array<string, User> $vendedores */
    private function crearMensajesDemo(Lead $lead, array $vendedores, int $indice): void
    {
        $momento = now()->subHours($indice + 1);
        $nombre = explode(' ', (string) $lead->nombre)[0];

        MensajeWhatsapp::updateOrCreate(
            ['meta_message_id' => 'demo-inicial-'.$lead->telefono_normalizado],
            [
                'lead_id' => $lead->id,
                'canal_whatsapp_id' => $lead->canal_whatsapp_id,
                'direccion' => 'entrante',
                'tipo' => 'texto',
                'contenido' => $indice % 3 === 0
                    ? 'Hola, necesito información. ¿Me pueden ayudar?'
                    : 'Buenas, quisiera conocer disponibilidad y precio.',
                'estado' => 'recibido',
                'ocurrio_at' => $momento,
            ]
        );

        if ($lead->etapa->slug === 'menu') {
            return;
        }

        MensajeWhatsapp::updateOrCreate(
            ['meta_message_id' => 'demo-respuesta-'.$lead->telefono_normalizado],
            [
                'lead_id' => $lead->id,
                'canal_whatsapp_id' => $lead->canal_whatsapp_id,
                'enviado_por_id' => $lead->vendedor_id ?? $vendedores['central']->id,
                'direccion' => 'saliente',
                'tipo' => 'texto',
                'contenido' => "Hola {$nombre}, gracias por escribir a GISECA. Con gusto te ayudamos con tu consulta.",
                'estado' => 'simulado',
                'ocurrio_at' => $momento->copy()->addMinutes(4),
            ]
        );
    }
}
