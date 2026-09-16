<?php

namespace App\Http\Controllers;

use App\Models\CanalWhatsapp;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use App\Services\MetaWhatsappService;
use App\Services\Permisos;
use App\Services\Telefono;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $puedeAdministrar = $this->puedeAdministrar($usuario);
        $q = trim((string) $request->get('q', ''));
        $canalId = $request->integer('canal');
        $vendedorId = $puedeAdministrar ? $request->integer('vendedor') : 0;
        $seguimiento = (string) $request->get('seguimiento', '');

        $leads = $this->consultaVisible($usuario)
            ->with(['etapa', 'canalWhatsapp', 'vendedor', 'ultimoMensajeWhatsapp'])
            ->withCount('mensajesWhatsapp')
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->where(function (Builder $sub) use ($q): void {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%")
                        ->orWhere('telefono_normalizado', 'like', "%{$q}%")
                        ->orWhere('empresa', 'like', "%{$q}%");
                });
            })
            ->when($canalId > 0, fn (Builder $query) => $query->where('canal_whatsapp_id', $canalId))
            ->when($vendedorId > 0, fn (Builder $query) => $query->where('vendedor_id', $vendedorId))
            ->latest('etapa_actualizada_at')
            ->latest('id')
            ->get();

        $hace48Horas = now()->subHours(48);
        $hace4Horas = now()->subHours(4);
        $leads = $this->filtrarSeguimiento($leads, $seguimiento, $hace4Horas);
        $estadisticas = [
            'total' => $leads->count(),
            'clientes' => $leads->whereNotNull('cliente_id')->count(),
            'valor' => $leads->sum('valor_estimado'),
            'pendientes' => $leads->filter(fn (Lead $lead): bool => $lead->etapa->tipo === 'activa')->count(),
            'sin_responder' => $leads->filter(fn (Lead $lead): bool => $this->sinResponder($lead))->count(),
            'vencidos' => $leads->filter(fn (Lead $lead): bool => $this->sinResponder($lead) && $lead->ultimoMensajeWhatsapp?->ocurrio_at?->lessThan($hace4Horas))->count(),
            'calientes' => $leads->filter(fn (Lead $lead): bool => $lead->ultima_interaccion_at?->greaterThanOrEqualTo($hace48Horas) ?? false)->count(),
            'frios' => $leads->filter(fn (Lead $lead): bool => ! $lead->ultima_interaccion_at || $lead->ultima_interaccion_at->lessThan($hace48Horas))->count(),
        ];

        return view('crm.index', [
            'leads' => $leads,
            'etapas' => EtapaCrm::activas()->get(),
            'canales' => $this->canalesVisibles($usuario),
            'vendedores' => $puedeAdministrar ? $this->vendedoresCrm() : collect([$usuario]),
            'estadisticas' => $estadisticas,
            'puedeAdministrar' => $puedeAdministrar,
            'q' => $q,
            'canalId' => $canalId,
            'vendedorId' => $vendedorId,
            'seguimiento' => $seguimiento,
        ]);
    }

    public function showLead(Request $request, Lead $lead, MetaWhatsappService $whatsapp): JsonResponse
    {
        $lead = $this->consultaVisible($request->user())
            ->with(['etapa', 'canalWhatsapp', 'vendedor', 'mensajesWhatsapp.enviadoPor'])
            ->findOrFail($lead->id);
        $puedeEnviar = $whatsapp->canalPuedeEnviar($lead->canalWhatsapp);

        return response()->json([
            'lead' => [
                'id' => $lead->id,
                'nombre' => $lead->nombre ?: 'Sin nombre',
                'telefono' => $lead->telefono,
                'empresa' => $lead->empresa,
                'ciudad' => $lead->ciudad,
                'etapa_crm_id' => $lead->etapa_crm_id,
                'etapa' => $lead->etapa->nombre,
                'vendedor_id' => $lead->vendedor_id,
                'vendedor' => $lead->vendedor?->name,
                'canal' => $lead->canalWhatsapp?->nombre,
                'valor_estimado' => $lead->valor_estimado,
                'notas' => $lead->notas,
                'puede_enviar' => $puedeEnviar,
                'motivo_envio' => $puedeEnviar ? null : 'Configura token y Phone Number ID para enviar desde Meta.',
                'ultima_interaccion' => $lead->ultima_interaccion_at?->format('d/m/Y H:i:s'),
                'creado' => $lead->created_at?->format('d/m/Y H:i:s'),
                'etapa_actualizada' => $lead->etapa_actualizada_at?->format('d/m/Y H:i:s'),
            ],
            'mensajes' => $lead->mensajesWhatsapp->map(fn (MensajeWhatsapp $mensaje): array => $this->mensajeJson($mensaje)),
        ]);
    }

    public function enviarMensaje(Request $request, Lead $lead, MetaWhatsappService $whatsapp): JsonResponse
    {
        $lead = $this->consultaVisible($request->user())
            ->with('canalWhatsapp')
            ->findOrFail($lead->id);
        $data = $request->validate([
            'mensaje' => ['nullable', 'required_without:archivo', 'string', 'max:4096'],
            'archivo' => ['nullable', 'file', 'max:102400'],
        ]);

        $mensaje = $request->hasFile('archivo')
            ? $whatsapp->enviarArchivo($lead, $request->user(), $request->file('archivo'), $data['mensaje'] ?? null)
            : $whatsapp->enviarTexto($lead, $request->user(), trim((string) $data['mensaje']));

        return response()->json([
            'message' => 'Mensaje enviado.',
            'mensaje' => $this->mensajeJson($mensaje->load('enviadoPor')),
        ]);
    }

    public function reintentarMensaje(Request $request, Lead $lead, MensajeWhatsapp $mensaje, MetaWhatsappService $whatsapp): JsonResponse
    {
        $lead = $this->consultaVisible($request->user())
            ->with('canalWhatsapp')
            ->findOrFail($lead->id);

        if ($mensaje->lead_id !== $lead->id || $mensaje->direccion !== 'saliente' || $mensaje->estado !== 'error') {
            abort(404);
        }

        $nuevoMensaje = $whatsapp->reenviarMensaje($mensaje->loadMissing('lead.canalWhatsapp'), $request->user());

        return response()->json([
            'message' => 'Mensaje reenviado.',
            'mensaje' => $this->mensajeJson($nuevoMensaje->load('enviadoPor')),
        ]);
    }

    public function cambiarEtapa(Request $request, Lead $lead): JsonResponse
    {
        $lead = $this->consultaVisible($request->user())->findOrFail($lead->id);
        $data = $request->validate([
            'etapa_crm_id' => ['required', Rule::exists('etapas_crm', 'id')->where('activa', true)],
        ]);
        $etapa = EtapaCrm::findOrFail($data['etapa_crm_id']);

        $this->aplicarEtapa($lead, $etapa);

        return response()->json([
            'message' => 'Etapa actualizada.',
            'etapa' => ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'color' => $etapa->color],
        ]);
    }

    public function actualizarFicha(Request $request, Lead $lead): JsonResponse
    {
        $usuario = $request->user();
        $lead = $this->consultaVisible($usuario)->findOrFail($lead->id);
        $data = $request->validate([
            'etapa_crm_id' => ['required', Rule::exists('etapas_crm', 'id')->where('activa', true)],
            'vendedor_id' => ['nullable', 'integer', 'exists:users,id'],
            'valor_estimado' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);
        $etapa = EtapaCrm::findOrFail($data['etapa_crm_id']);

        if ($this->puedeAdministrar($usuario)) {
            $lead->vendedor_id = $this->resolverVendedor($data['vendedor_id'] ?? null)?->id;
        }

        $lead->valor_estimado = $data['valor_estimado'] ?? 0;
        $lead->notas = $data['notas'] ?? null;
        $this->aplicarEtapa($lead, $etapa);

        return response()->json([
            'message' => 'Ficha actualizada.',
            'vendedor' => $lead->fresh()->vendedor?->name,
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $puedeAdministrar = $this->puedeAdministrar($usuario);
        $data = $request->validate($this->reglasLead());
        $canal = $this->resolverCanalVisible($usuario, $data['canal_whatsapp_id'] ?? null);
        $vendedorId = $puedeAdministrar
            ? $this->resolverVendedor($data['vendedor_id'] ?? null)?->id
            : $usuario->id;

        $telefonoNormalizado = $this->normalizarTelefono($data['telefono']);

        Lead::create([
            ...$data,
            'etapa_crm_id' => $data['etapa_crm_id'] ?? EtapaCrm::inicial()->id,
            'canal_whatsapp_id' => $canal?->id,
            'vendedor_id' => $vendedorId,
            'telefono_normalizado' => $telefonoNormalizado,
            'origen' => 'manual',
            'etapa_actualizada_at' => now(),
        ]);

        return redirect()->route('crm.index')->with('exito', 'Lead creado correctamente.');
    }

    public function updateLead(Request $request, Lead $lead): RedirectResponse
    {
        $usuario = $request->user();
        $lead = $this->consultaVisible($usuario)->findOrFail($lead->id);
        $puedeAdministrar = $this->puedeAdministrar($usuario);
        $data = $request->validate($this->reglasLead(false));
        $canal = $this->resolverCanalVisible($usuario, $data['canal_whatsapp_id'] ?? null, true);
        $etapa = EtapaCrm::findOrFail($data['etapa_crm_id']);
        $cambioEtapa = $lead->etapa_crm_id !== $etapa->id;

        if ($puedeAdministrar) {
            $data['vendedor_id'] = $this->resolverVendedor($data['vendedor_id'] ?? null)?->id;
        } else {
            unset($data['vendedor_id']);
        }

        $data['canal_whatsapp_id'] = $canal?->id;
        $data['telefono_normalizado'] = $this->normalizarTelefono($data['telefono']);

        if ($cambioEtapa) {
            $data['etapa_actualizada_at'] = now();
        }

        $data['cerrado_at'] = in_array($etapa->tipo, [EtapaCrm::GANADA, EtapaCrm::PERDIDA], true)
            ? ($lead->cerrado_at ?? now())
            : null;

        $lead->update($data);

        return redirect()->route('crm.index', $request->only(['q', 'etapa', 'canal']))
            ->with('exito', 'Lead actualizado correctamente.');
    }

    public function canales(): View
    {
        return view('crm.canales', [
            'canales' => CanalWhatsapp::with('vendedores')->orderBy('nombre')->get(),
            'vendedores' => $this->vendedoresCrm(),
            'webhookUrl' => route('meta.whatsapp.webhook.receive'),
            'webhookVerifyUrl' => route('meta.whatsapp.webhook.verify'),
            'webhookVerifyTokenConfigurado' => filled(config('services.meta_whatsapp.webhook_verify_token')),
            'appSecretConfigurado' => filled(config('services.meta_whatsapp.app_secret')),
            'tokenGlobalConfigurado' => filled(config('services.meta_whatsapp.access_token')),
        ]);
    }

    public function diagnostico(): View
    {
        $canales = CanalWhatsapp::with('vendedores')
            ->withCount('leads')
            ->orderBy('nombre')
            ->get();
        $canalesActivos = $canales->where('activo', true);
        $tokenGlobalConfigurado = filled(config('services.meta_whatsapp.access_token'));
        $verifyTokenConfigurado = filled(config('services.meta_whatsapp.webhook_verify_token'));
        $appSecretConfigurado = filled(config('services.meta_whatsapp.app_secret'));
        $appUrl = (string) config('app.url');
        $host = (string) parse_url($appUrl, PHP_URL_HOST);
        $esHttps = str_starts_with($appUrl, 'https://');
        $esDominioLocal = $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
        $storagePublicoListo = is_dir(public_path('storage'));
        $canalesConCredenciales = $canalesActivos
            ->filter(fn (CanalWhatsapp $canal): bool => $this->canalTieneCredencialesMeta($canal, $tokenGlobalConfigurado))
            ->count();
        $canalesConVendedores = $canalesActivos
            ->filter(fn (CanalWhatsapp $canal): bool => $canal->vendedores->isNotEmpty())
            ->count();
        $canalesValidados = $canalesActivos
            ->filter(fn (CanalWhatsapp $canal): bool => $canal->estado === 'conectado' && filled($canal->meta_verificado_at))
            ->count();

        $checks = [
            $this->checkDiagnostico(
                'Dominio público HTTPS',
                $esHttps && ! $esDominioLocal,
                $esDominioLocal ? 'Dominio local detectado: ajusta APP_URL al dominio público antes de conectar Meta.' : ($esHttps ? 'APP_URL usa HTTPS.' : 'APP_URL no usa HTTPS.'),
            ),
            $this->checkDiagnostico(
                'Verify token',
                $verifyTokenConfigurado,
                $verifyTokenConfigurado ? 'Token de verificación configurado.' : 'Falta META_WHATSAPP_WEBHOOK_VERIFY_TOKEN.',
            ),
            $this->checkDiagnostico(
                'Firma de webhook',
                $appSecretConfigurado,
                $appSecretConfigurado ? 'App secret configurado para validar firmas de Meta.' : 'Falta META_WHATSAPP_APP_SECRET para validar X-Hub-Signature-256.',
                'advertencia',
            ),
            $this->checkDiagnostico(
                'Storage público',
                $storagePublicoListo,
                $storagePublicoListo ? 'Los adjuntos pueden publicarse desde storage.' : 'Falta el enlace public/storage para ver adjuntos del chat.',
                'advertencia',
            ),
            $this->checkDiagnostico(
                'Líneas activas',
                $canalesActivos->isNotEmpty(),
                $canalesActivos->isNotEmpty() ? $canalesActivos->count().' línea(s) activa(s).' : 'Registra al menos una línea comercial.',
            ),
            $this->checkDiagnostico(
                'Credenciales por línea',
                $canalesActivos->isNotEmpty() && $canalesConCredenciales === $canalesActivos->count(),
                $canalesConCredenciales.'/'.$canalesActivos->count().' línea(s) activa(s) con Phone Number ID y token.',
            ),
            $this->checkDiagnostico(
                'Asignación comercial',
                $canalesActivos->isNotEmpty() && $canalesConVendedores === $canalesActivos->count(),
                $canalesConVendedores.'/'.$canalesActivos->count().' línea(s) activa(s) con vendedores asignados.',
            ),
            $this->checkDiagnostico(
                'Validación Meta',
                $canalesActivos->isNotEmpty() && $canalesValidados === $canalesActivos->count(),
                $canalesValidados.'/'.$canalesActivos->count().' línea(s) activa(s) validadas contra Meta.',
                'advertencia',
            ),
        ];
        $bloqueos = collect($checks)->filter(fn (array $check): bool => ! $check['ok'] && $check['nivel'] === 'bloqueo')->count();
        $advertencias = collect($checks)->filter(fn (array $check): bool => ! $check['ok'] && $check['nivel'] === 'advertencia')->count();

        return view('crm.diagnostico', [
            'canales' => $canales,
            'checks' => $checks,
            'resumen' => [
                'estado' => $bloqueos > 0 ? 'Revisar bloqueos' : ($advertencias > 0 ? 'Listo con alertas' : 'Listo para pruebas'),
                'bloqueos' => $bloqueos,
                'advertencias' => $advertencias,
                'total_canales' => $canales->count(),
                'canales_activos' => $canalesActivos->count(),
                'canales_con_credenciales' => $canalesConCredenciales,
                'canales_validados' => $canalesValidados,
            ],
            'appUrl' => $appUrl,
            'webhookUrl' => route('meta.whatsapp.webhook.receive'),
            'webhookVerifyUrl' => route('meta.whatsapp.webhook.verify'),
            'tokenGlobalConfigurado' => $tokenGlobalConfigurado,
            'storagePublicoListo' => $storagePublicoListo,
        ]);
    }

    public function storeCanal(Request $request): RedirectResponse
    {
        $data = $request->validate($this->reglasCanal());
        $telefonoNormalizado = $this->normalizarTelefono($data['telefono']);
        $this->validarTelefonoCanalUnico($telefonoNormalizado);

        $canal = CanalWhatsapp::create([
            ...Arr::except($data, 'vendedores'),
            'telefono_normalizado' => $telefonoNormalizado,
            'estado' => 'pendiente',
            'activo' => $request->boolean('activo'),
        ]);
        $canal->vendedores()->sync($this->idsVendedoresValidos($data['vendedores'] ?? []));

        return redirect()->route('crm.canales')->with('exito', 'Línea comercial creada.');
    }

    public function updateCanal(Request $request, CanalWhatsapp $canalWhatsapp): RedirectResponse
    {
        $data = $request->validate($this->reglasCanal($canalWhatsapp));
        $telefonoNormalizado = $this->normalizarTelefono($data['telefono']);
        $this->validarTelefonoCanalUnico($telefonoNormalizado, $canalWhatsapp->id);
        $atributos = [
            ...Arr::except($data, ['vendedores', 'access_token']),
            'telefono_normalizado' => $telefonoNormalizado,
            'activo' => $request->boolean('activo'),
        ];

        if (filled($data['access_token'] ?? null)) {
            $atributos['access_token'] = $data['access_token'];
        }

        $canalWhatsapp->update($atributos);
        $canalWhatsapp->vendedores()->sync($this->idsVendedoresValidos($data['vendedores'] ?? []));

        return redirect()->route('crm.canales')->with('exito', 'Línea comercial actualizada.');
    }

    public function probarCanalMeta(Request $request, CanalWhatsapp $canalWhatsapp, MetaWhatsappService $whatsapp): JsonResponse
    {
        return response()->json($whatsapp->probarCanal($canalWhatsapp));
    }

    /** @return array<string, mixed> */
    private function reglasLead(bool $crear = true): array
    {
        return [
            'nombre' => ['nullable', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'canal_whatsapp_id' => ['nullable', 'integer', 'exists:canal_whatsapps,id'],
            'etapa_crm_id' => [$crear ? 'nullable' : 'required', Rule::exists('etapas_crm', 'id')->where('activa', true)],
            'vendedor_id' => ['nullable', 'integer', 'exists:users,id'],
            'tipo_consulta' => ['nullable', Rule::in(['informacion', 'precio', 'otro'])],
            'valor_estimado' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'motivo_perdida' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasCanal(?CanalWhatsapp $canal = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'telefono' => ['required', 'string', 'max:30'],
            'waba_id' => ['nullable', 'string', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'max:255', Rule::unique('canal_whatsapps', 'phone_number_id')->ignore($canal)],
            'access_token' => ['nullable', 'string'],
            'graph_version' => ['nullable', 'string', 'max:20'],
            'activo' => ['nullable', 'boolean'],
            'vendedores' => ['nullable', 'array'],
            'vendedores.*' => ['integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, mixed> */
    private function mensajeJson(MensajeWhatsapp $mensaje): array
    {
        return [
            'id' => $mensaje->id,
            'meta_message_id' => $mensaje->meta_message_id,
            'direccion' => $mensaje->direccion,
            'tipo' => $mensaje->tipo,
            'contenido' => $mensaje->contenido,
            'archivo_url' => $mensaje->archivo_url,
            'mime_type' => $mensaje->mime_type,
            'nombre_archivo' => $mensaje->nombre_archivo,
            'tamano_archivo' => $mensaje->tamano_archivo,
            'estado' => $mensaje->estado,
            'estado_texto' => $this->estadoMensajeTexto($mensaje),
            'estado_icono' => $this->estadoMensajeIcono($mensaje),
            'puede_reintentar' => $mensaje->direccion === 'saliente' && $mensaje->estado === 'error',
            'autor' => $mensaje->enviadoPor?->name,
            'hora' => $mensaje->ocurrio_at->format('H:i'),
            'fecha' => $mensaje->ocurrio_at->format('d/m/Y'),
        ];
    }

    private function filtrarSeguimiento(Collection $leads, string $seguimiento, Carbon $hace4Horas): Collection
    {
        return match ($seguimiento) {
            'sin_responder' => $leads->filter(fn (Lead $lead): bool => $this->sinResponder($lead))->values(),
            'vencidos' => $leads->filter(fn (Lead $lead): bool => $this->sinResponder($lead) && $lead->ultimoMensajeWhatsapp?->ocurrio_at?->lessThan($hace4Horas))->values(),
            'errores' => $leads->filter(fn (Lead $lead): bool => $lead->ultimoMensajeWhatsapp?->direccion === 'saliente' && $lead->ultimoMensajeWhatsapp?->estado === 'error')->values(),
            default => $leads,
        };
    }

    private function estadoMensajeTexto(MensajeWhatsapp $mensaje): string
    {
        if ($mensaje->direccion === 'entrante') {
            return 'Recibido';
        }

        return match ($mensaje->estado) {
            'pendiente' => 'Pendiente',
            'enviado' => 'Enviado',
            'entregado' => 'Entregado',
            'leido' => 'Leído',
            'error' => 'Error',
            'simulado' => 'Simulado',
            default => ucfirst($mensaje->estado),
        };
    }

    private function estadoMensajeIcono(MensajeWhatsapp $mensaje): string
    {
        if ($mensaje->direccion === 'entrante') {
            return 'bi-check';
        }

        return match ($mensaje->estado) {
            'pendiente' => 'bi-clock',
            'enviado' => 'bi-check',
            'entregado' => 'bi-check2-all',
            'leido' => 'bi-check2-all',
            'error' => 'bi-exclamation-triangle-fill',
            'simulado' => 'bi-lightning-charge-fill',
            default => 'bi-check',
        };
    }

    private function consultaVisible(User $usuario): Builder
    {
        $query = Lead::query();

        if ($this->puedeAdministrar($usuario)) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($usuario): void {
            $visible->where('vendedor_id', $usuario->id)
                ->orWhere(function (Builder $sinAsignar) use ($usuario): void {
                    $sinAsignar->whereNull('vendedor_id')
                        ->whereHas('canalWhatsapp.vendedores', fn (Builder $vendedores) => $vendedores->whereKey($usuario->id));
                });
        });
    }

    private function canalesVisibles(User $usuario): Collection
    {
        if ($this->puedeAdministrar($usuario)) {
            return CanalWhatsapp::activos()->orderBy('nombre')->get();
        }

        return $usuario->canalesWhatsapp()->activos()->orderBy('nombre')->get();
    }

    private function resolverCanalVisible(User $usuario, mixed $canalId, bool $incluirInactivo = false): ?CanalWhatsapp
    {
        if (! $canalId) {
            return null;
        }

        $query = $this->puedeAdministrar($usuario)
            ? CanalWhatsapp::query()
            : $usuario->canalesWhatsapp();

        if (! $incluirInactivo) {
            $query->where('activo', true);
        }

        return $query->findOrFail($canalId);
    }

    private function vendedoresCrm(): Collection
    {
        return User::query()
            ->where('activo', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $usuario): bool => Permisos::puede($usuario, 'crm'))
            ->values();
    }

    private function resolverVendedor(mixed $vendedorId): ?User
    {
        if (! $vendedorId) {
            return null;
        }

        $vendedor = User::query()->where('activo', true)->findOrFail($vendedorId);

        if (! Permisos::puede($vendedor, 'crm')) {
            throw ValidationException::withMessages([
                'vendedor_id' => 'El usuario seleccionado no tiene acceso al CRM.',
            ]);
        }

        return $vendedor;
    }

    /** @param array<int, mixed> $ids */
    private function idsVendedoresValidos(array $ids): array
    {
        return User::query()
            ->where('activo', true)
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (User $usuario): bool => Permisos::puede($usuario, 'crm'))
            ->pluck('id')
            ->all();
    }

    private function normalizarTelefono(string $telefono): string
    {
        $normalizado = Telefono::normalizar($telefono);

        if (strlen($normalizado) < 8) {
            throw ValidationException::withMessages([
                'telefono' => 'Ingresa un número de teléfono válido.',
            ]);
        }

        return $normalizado;
    }

    private function validarTelefonoCanalUnico(string $telefonoNormalizado, ?int $ignorarId = null): void
    {
        $existe = CanalWhatsapp::withTrashed()
            ->where('telefono_normalizado', $telefonoNormalizado)
            ->when($ignorarId, fn (Builder $query) => $query->where('id', '!=', $ignorarId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'telefono' => 'Este número ya está registrado como línea comercial.',
            ]);
        }
    }

    private function aplicarEtapa(Lead $lead, EtapaCrm $etapa): void
    {
        if ($lead->etapa_crm_id !== $etapa->id) {
            $lead->etapa_crm_id = $etapa->id;
            $lead->etapa_actualizada_at = now();
        }

        $lead->cerrado_at = in_array($etapa->tipo, [EtapaCrm::GANADA, EtapaCrm::PERDIDA], true)
            ? ($lead->cerrado_at ?? now())
            : null;
        $lead->save();
    }

    private function puedeAdministrar(User $usuario): bool
    {
        return Permisos::puede($usuario, 'crm.administrar');
    }

    private function sinResponder(Lead $lead): bool
    {
        return $lead->etapa->tipo === 'activa'
            && $lead->ultimoMensajeWhatsapp?->direccion === 'entrante';
    }

    private function canalTieneCredencialesMeta(CanalWhatsapp $canal, bool $tokenGlobalConfigurado): bool
    {
        return filled($canal->phone_number_id)
            && (filled($canal->access_token) || $tokenGlobalConfigurado);
    }

    /**
     * @return array{titulo: string, ok: bool, detalle: string, nivel: string, icono: string}
     */
    private function checkDiagnostico(string $titulo, bool $ok, string $detalle, string $nivel = 'bloqueo'): array
    {
        return [
            'titulo' => $titulo,
            'ok' => $ok,
            'detalle' => $detalle,
            'nivel' => $nivel,
            'icono' => $ok ? 'bi-check-circle-fill' : ($nivel === 'bloqueo' ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill'),
        ];
    }
}
