<?php

namespace App\Services;

use App\Models\CanalWhatsapp;
use App\Models\EtapaCrm;
use App\Models\Lead;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaWhatsappService
{
    /** @return array{mensajes:int,statuses:int} */
    public function procesarWebhook(array $payload): array
    {
        $procesados = ['mensajes' => 0, 'statuses' => 0];

        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field') !== 'messages') {
                    continue;
                }

                $value = Arr::get($change, 'value', []);
                $canal = $this->resolverCanal($value);

                foreach (Arr::get($value, 'statuses', []) as $status) {
                    $procesados['statuses'] += $this->actualizarEstado($status);
                }

                foreach (Arr::get($value, 'messages', []) as $message) {
                    $procesados['mensajes'] += $this->registrarMensajeEntrante($message, $value, $canal);
                }
            }
        }

        return $procesados;
    }

    public function enviarTexto(Lead $lead, User $usuario, string $texto): MensajeWhatsapp
    {
        $lead->loadMissing('canalWhatsapp');
        $canal = $lead->canalWhatsapp;

        if (! $canal instanceof CanalWhatsapp) {
            throw ValidationException::withMessages([
                'mensaje' => 'El lead no tiene una línea comercial de WhatsApp asociada.',
            ]);
        }

        $token = $this->tokenParaCanal($canal);
        $phoneNumberId = $canal->phone_number_id;

        if (! $token || ! $phoneNumberId) {
            throw ValidationException::withMessages([
                'mensaje' => 'Falta configurar el token o Phone Number ID de esta línea.',
            ]);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $lead->telefono_normalizado,
            'type' => 'text',
            'text' => ['body' => $texto],
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.meta_whatsapp.timeout', 12))
                ->post($this->urlMensajes($canal), $payload)
                ->throw();
        } catch (RequestException $exception) {
            $canal->forceFill([
                'estado' => 'error',
                'ultimo_error' => $exception->response?->json('error.message') ?? $exception->getMessage(),
            ])->save();

            throw ValidationException::withMessages([
                'mensaje' => 'Meta rechazó el envío: '.($canal->ultimo_error ?: 'revisa las credenciales.'),
            ]);
        }

        $mensaje = MensajeWhatsapp::create([
            'lead_id' => $lead->id,
            'canal_whatsapp_id' => $canal->id,
            'enviado_por_id' => $usuario->id,
            'meta_message_id' => Arr::get($response->json(), 'messages.0.id'),
            'direccion' => 'saliente',
            'tipo' => 'texto',
            'contenido' => $texto,
            'estado' => 'enviado',
            'payload' => $payload,
            'ocurrio_at' => now(),
        ]);

        $lead->forceFill(['ultima_interaccion_at' => now()])->save();
        $canal->forceFill(['estado' => 'conectado', 'ultimo_error' => null])->save();

        return $mensaje;
    }

    public function enviarArchivo(Lead $lead, User $usuario, UploadedFile $archivo, ?string $caption = null): MensajeWhatsapp
    {
        $lead->loadMissing('canalWhatsapp');
        $canal = $lead->canalWhatsapp;

        if (! $canal instanceof CanalWhatsapp) {
            throw ValidationException::withMessages([
                'mensaje' => 'El lead no tiene una línea comercial de WhatsApp asociada.',
            ]);
        }

        $token = $this->tokenParaCanal($canal);
        $phoneNumberId = $canal->phone_number_id;

        if (! $token || ! $phoneNumberId) {
            throw ValidationException::withMessages([
                'mensaje' => 'Falta configurar el token o Phone Number ID de esta línea.',
            ]);
        }

        $mimeType = $archivo->getMimeType() ?: $archivo->getClientMimeType() ?: 'application/octet-stream';
        $tipo = $this->tipoSalidaDesdeMime($mimeType);

        if (! $tipo) {
            throw ValidationException::withMessages([
                'archivo' => 'Este tipo de archivo aún no es compatible con WhatsApp Cloud API.',
            ]);
        }

        $fecha = now();
        $nombreOriginal = $this->nombreArchivoSeguro($archivo->getClientOriginalName() ?: 'archivo');
        $path = 'whatsapp/'.$fecha->format('Y/m').'/'.Str::uuid().'-'.$nombreOriginal;
        Storage::disk('public')->putFileAs(dirname($path), $archivo, basename($path));
        $rutaAbsoluta = Storage::disk('public')->path($path);
        $caption = trim((string) $caption);
        $mediaStream = fopen($rutaAbsoluta, 'r');

        try {
            $mediaResponse = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.meta_whatsapp.timeout', 12))
                ->attach('file', $mediaStream, $nombreOriginal)
                ->post($this->urlSubirMedia($canal), [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ])
                ->throw();

            $mediaId = Arr::get($mediaResponse->json(), 'id');

            if (! $mediaId) {
                throw ValidationException::withMessages([
                    'archivo' => 'Meta no devolvió el identificador del archivo subido.',
                ]);
            }

            $payload = $this->payloadMedia($lead, $tipo, (string) $mediaId, $caption, $nombreOriginal);
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.meta_whatsapp.timeout', 12))
                ->post($this->urlMensajes($canal), $payload)
                ->throw();
        } catch (RequestException $exception) {
            $canal->forceFill([
                'estado' => 'error',
                'ultimo_error' => $exception->response?->json('error.message') ?? $exception->getMessage(),
            ])->save();

            throw ValidationException::withMessages([
                'mensaje' => 'Meta rechazó el envío: '.($canal->ultimo_error ?: 'revisa las credenciales.'),
            ]);
        } finally {
            if (is_resource($mediaStream)) {
                fclose($mediaStream);
            }
        }

        $mensaje = MensajeWhatsapp::create([
            'lead_id' => $lead->id,
            'canal_whatsapp_id' => $canal->id,
            'enviado_por_id' => $usuario->id,
            'meta_message_id' => Arr::get($response->json(), 'messages.0.id'),
            'meta_media_id' => (string) $mediaId,
            'direccion' => 'saliente',
            'tipo' => $this->tipoLocalDesdeTipoMeta($tipo),
            'contenido' => $caption !== '' ? $caption : null,
            'archivo_url' => Storage::disk('public')->url($path),
            'mime_type' => $mimeType,
            'nombre_archivo' => $nombreOriginal,
            'tamano_archivo' => $archivo->getSize(),
            'estado' => 'enviado',
            'payload' => $payload,
            'ocurrio_at' => now(),
        ]);

        $lead->forceFill(['ultima_interaccion_at' => now()])->save();
        $canal->forceFill(['estado' => 'conectado', 'ultimo_error' => null])->save();

        return $mensaje;
    }

    public function reenviarMensaje(MensajeWhatsapp $mensaje, User $usuario): MensajeWhatsapp
    {
        if ($mensaje->direccion !== 'saliente' || $mensaje->estado !== 'error') {
            throw ValidationException::withMessages([
                'mensaje' => 'Solo se pueden reintentar mensajes salientes con error.',
            ]);
        }

        $lead = $mensaje->lead()->with('canalWhatsapp')->firstOrFail();

        if (in_array($mensaje->tipo, ['imagen', 'documento', 'audio', 'video'], true) && $mensaje->archivo_url) {
            $storagePath = $this->storagePathDesdeArchivoUrl($mensaje->archivo_url);

            if (! $storagePath || ! Storage::disk('public')->exists($storagePath)) {
                throw ValidationException::withMessages([
                    'mensaje' => 'No se encontró el archivo original para reenviar.',
                ]);
            }

            $archivo = new UploadedFile(
                Storage::disk('public')->path($storagePath),
                $mensaje->nombre_archivo ?: basename($storagePath),
                $mensaje->mime_type ?: 'application/octet-stream',
                null,
                true
            );

            return $this->enviarArchivo($lead, $usuario, $archivo, $mensaje->contenido);
        }

        if (! $mensaje->contenido) {
            throw ValidationException::withMessages([
                'mensaje' => 'El mensaje original no tiene contenido para reenviar.',
            ]);
        }

        return $this->enviarTexto($lead, $usuario, $mensaje->contenido);
    }

    public function canalPuedeEnviar(?CanalWhatsapp $canal): bool
    {
        return $canal instanceof CanalWhatsapp
            && filled($canal->phone_number_id)
            && filled($this->tokenParaCanal($canal));
    }

    /** @return array{ok: bool, message: string, canal: array<string, mixed>} */
    public function probarCanal(CanalWhatsapp $canal): array
    {
        $token = $this->tokenParaCanal($canal);

        if (! $token || ! $canal->phone_number_id) {
            $canal->forceFill([
                'estado' => 'error',
                'ultimo_error' => 'Falta configurar token de acceso o Phone Number ID.',
            ])->save();

            throw ValidationException::withMessages([
                'canal' => 'Falta configurar token de acceso o Phone Number ID.',
            ]);
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.meta_whatsapp.timeout', 12))
                ->get($this->urlTelefono($canal), [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating',
                ])
                ->throw();
        } catch (RequestException $exception) {
            $canal->forceFill([
                'estado' => 'error',
                'ultimo_error' => $exception->response?->json('error.message') ?? $exception->getMessage(),
            ])->save();

            throw ValidationException::withMessages([
                'canal' => 'Meta rechazó la validación: '.($canal->ultimo_error ?: 'revisa las credenciales.'),
            ]);
        }

        $datos = $response->json();
        $canal->forceFill([
            'estado' => 'conectado',
            'ultimo_error' => null,
            'meta_display_phone_number' => Arr::get($datos, 'display_phone_number'),
            'meta_verified_name' => Arr::get($datos, 'verified_name'),
            'meta_quality_rating' => Arr::get($datos, 'quality_rating'),
            'meta_code_verification_status' => Arr::get($datos, 'code_verification_status'),
            'meta_verificado_at' => now(),
        ])->save();

        return [
            'ok' => true,
            'message' => 'Conexión validada con Meta.',
            'canal' => $this->resumenCanal($canal->fresh()),
        ];
    }

    public function validarFirma(?string $firma, string $contenido): bool
    {
        $appSecret = config('services.meta_whatsapp.app_secret');

        if (! $appSecret) {
            return true;
        }

        if (! $firma || ! str_starts_with($firma, 'sha256=')) {
            return false;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $contenido, $appSecret);

        return hash_equals($esperada, $firma);
    }

    private function resolverCanal(array $value): ?CanalWhatsapp
    {
        $phoneNumberId = Arr::get($value, 'metadata.phone_number_id');
        $telefono = Arr::get($value, 'metadata.display_phone_number');

        if ($phoneNumberId) {
            $canal = CanalWhatsapp::where('phone_number_id', $phoneNumberId)->first();

            if ($canal instanceof CanalWhatsapp) {
                return $canal;
            }
        }

        if ($telefono) {
            return CanalWhatsapp::where('telefono_normalizado', Telefono::normalizar($telefono))->first();
        }

        return null;
    }

    private function registrarMensajeEntrante(array $message, array $value, ?CanalWhatsapp $canal): int
    {
        $metaMessageId = Arr::get($message, 'id');

        if ($metaMessageId && MensajeWhatsapp::where('meta_message_id', $metaMessageId)->exists()) {
            return 0;
        }

        $telefono = Telefono::normalizar((string) Arr::get($message, 'from', ''));
        $fecha = $this->fechaMeta(Arr::get($message, 'timestamp'));
        $lead = $this->resolverLead($telefono, $value, $canal, $fecha);
        $contenido = $this->contenidoMensaje($message);
        $mediaId = $this->mediaId($message);
        $media = $mediaId ? $this->descargarMedia($mediaId, $message, $canal, $fecha) : [];

        MensajeWhatsapp::create([
            'lead_id' => $lead->id,
            'canal_whatsapp_id' => $canal?->id,
            'meta_message_id' => $metaMessageId,
            'meta_media_id' => $mediaId,
            'direccion' => 'entrante',
            'tipo' => $this->tipoMensaje($message),
            'contenido' => $contenido,
            'archivo_url' => $media['archivo_url'] ?? null,
            'mime_type' => $media['mime_type'] ?? $this->mimeTypeMensaje($message),
            'nombre_archivo' => $media['nombre_archivo'] ?? $this->nombreArchivoMensaje($message, $mediaId),
            'tamano_archivo' => $media['tamano_archivo'] ?? null,
            'estado' => 'recibido',
            'payload' => $message,
            'ocurrio_at' => $fecha,
        ]);

        $lead->forceFill(['ultima_interaccion_at' => $fecha])->save();

        return 1;
    }

    private function resolverLead(string $telefono, array $value, ?CanalWhatsapp $canal, CarbonImmutable $fecha): Lead
    {
        $lead = Lead::query()
            ->where('telefono_normalizado', $telefono)
            ->when($canal, fn ($query) => $query->where('canal_whatsapp_id', $canal->id))
            ->latest('id')
            ->first();

        if ($lead instanceof Lead) {
            $nombre = Arr::get($value, 'contacts.0.profile.name');
            $cambios = [];

            if (! $lead->nombre && $nombre) {
                $cambios['nombre'] = $nombre;
            }

            if (! $lead->vendedor_id && $canal instanceof CanalWhatsapp) {
                $cambios['vendedor_id'] = $this->siguienteVendedorId($canal);
            }

            if ($cambios !== []) {
                $lead->forceFill($cambios)->save();
            }

            return $lead;
        }

        return Lead::create([
            'etapa_crm_id' => EtapaCrm::inicial()->id,
            'canal_whatsapp_id' => $canal?->id,
            'vendedor_id' => $canal instanceof CanalWhatsapp ? $this->siguienteVendedorId($canal) : null,
            'nombre' => Arr::get($value, 'contacts.0.profile.name'),
            'telefono' => $telefono,
            'telefono_normalizado' => $telefono,
            'origen' => 'whatsapp',
            'valor_estimado' => 0,
            'ultima_interaccion_at' => $fecha,
            'etapa_actualizada_at' => $fecha,
        ]);
    }

    private function siguienteVendedorId(CanalWhatsapp $canal): ?int
    {
        /** @var Collection<int, int> $vendedores */
        $vendedores = $canal->vendedores()
            ->where('activo', true)
            ->orderBy('users.id')
            ->pluck('users.id')
            ->values();

        if ($vendedores->isEmpty()) {
            return null;
        }

        if ($vendedores->count() === 1) {
            $vendedorId = (int) $vendedores->first();
            $canal->forceFill(['ultimo_vendedor_asignado_id' => $vendedorId])->save();

            return $vendedorId;
        }

        $ultimoAsignado = $canal->ultimo_vendedor_asignado_id;
        $indiceActual = $ultimoAsignado ? $vendedores->search((int) $ultimoAsignado, true) : false;
        $siguienteIndice = $indiceActual === false ? 0 : (((int) $indiceActual + 1) % $vendedores->count());
        $vendedorId = (int) $vendedores->get($siguienteIndice);

        $canal->forceFill(['ultimo_vendedor_asignado_id' => $vendedorId])->save();

        return $vendedorId;
    }

    private function actualizarEstado(array $status): int
    {
        $mensaje = MensajeWhatsapp::where('meta_message_id', Arr::get($status, 'id'))->first();

        if (! $mensaje instanceof MensajeWhatsapp) {
            return 0;
        }

        $mensaje->forceFill([
            'estado' => $this->estadoMensaje((string) Arr::get($status, 'status')),
            'payload' => array_merge($mensaje->payload ?? [], ['status' => $status]),
        ])->save();

        return 1;
    }

    private function contenidoMensaje(array $message): ?string
    {
        $type = Arr::get($message, 'type');

        return match ($type) {
            'text' => Arr::get($message, 'text.body'),
            'button' => Arr::get($message, 'button.text') ?: Arr::get($message, 'button.payload'),
            'interactive' => Arr::get($message, 'interactive.button_reply.title')
                ?: Arr::get($message, 'interactive.list_reply.title'),
            'image' => Arr::get($message, 'image.caption') ?: 'Imagen recibida',
            'audio' => 'Audio recibido',
            'document' => Arr::get($message, 'document.filename') ?: Arr::get($message, 'document.caption') ?: 'Documento recibido',
            'video' => Arr::get($message, 'video.caption') ?: 'Video recibido',
            'sticker' => 'Sticker recibido',
            'location' => trim((string) (Arr::get($message, 'location.name') ?: Arr::get($message, 'location.address') ?: 'Ubicación recibida')),
            default => 'Mensaje recibido',
        };
    }

    private function tipoMensaje(array $message): string
    {
        return match (Arr::get($message, 'type')) {
            'image', 'sticker' => 'imagen',
            'audio' => 'audio',
            'document' => 'documento',
            'video' => 'video',
            'button', 'interactive', 'location', 'contacts' => 'interactivo',
            default => 'texto',
        };
    }

    private function tipoSalidaDesdeMime(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/png' => 'image',
            'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg' => 'audio',
            'video/mp4', 'video/3gpp' => 'video',
            'text/plain',
            'application/pdf',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function payloadMedia(Lead $lead, string $tipo, string $mediaId, string $caption, string $nombreArchivo): array
    {
        $media = ['id' => $mediaId];

        if (in_array($tipo, ['document', 'image', 'video'], true) && $caption !== '') {
            $media['caption'] = $caption;
        }

        if ($tipo === 'document') {
            $media['filename'] = $nombreArchivo;
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $lead->telefono_normalizado,
            'type' => $tipo,
            $tipo => $media,
        ];
    }

    private function tipoLocalDesdeTipoMeta(string $tipo): string
    {
        return match ($tipo) {
            'image' => 'imagen',
            'audio' => 'audio',
            'video' => 'video',
            'document' => 'documento',
            default => 'texto',
        };
    }

    private function nombreArchivoSeguro(string $nombre): string
    {
        $extension = pathinfo($nombre, PATHINFO_EXTENSION);
        $base = Str::slug(pathinfo($nombre, PATHINFO_FILENAME)) ?: 'archivo';

        return $base.($extension ? '.'.Str::lower($extension) : '');
    }

    private function storagePathDesdeArchivoUrl(string $archivoUrl): ?string
    {
        $path = parse_url($archivoUrl, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return Str::after($path, 'storage/');
        }

        return str_starts_with($path, 'whatsapp/') ? $path : null;
    }

    private function mediaId(array $message): ?string
    {
        $type = Arr::get($message, 'type');

        if (! in_array($type, ['image', 'audio', 'document', 'video', 'sticker'], true)) {
            return null;
        }

        return Arr::get($message, $type.'.id');
    }

    /** @return array{archivo_url?: string, mime_type?: string, nombre_archivo?: string, tamano_archivo?: int} */
    private function descargarMedia(string $mediaId, array $message, ?CanalWhatsapp $canal, CarbonImmutable $fecha): array
    {
        if (! $canal instanceof CanalWhatsapp) {
            return [];
        }

        $token = $this->tokenParaCanal($canal);

        if (! $token) {
            return [];
        }

        try {
            $mediaResponse = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.meta_whatsapp.timeout', 12))
                ->get($this->urlMedia($canal, $mediaId), [
                    'phone_number_id' => $canal->phone_number_id,
                ])
                ->throw();

            $mediaData = $mediaResponse->json();
            $downloadUrl = Arr::get($mediaData, 'url');

            if (! $downloadUrl) {
                return [];
            }

            $downloadResponse = Http::withToken($token)
                ->timeout((int) config('services.meta_whatsapp.timeout', 12))
                ->get((string) $downloadUrl)
                ->throw();
        } catch (RequestException) {
            return [];
        }

        $mimeType = (string) (Arr::get($mediaData, 'mime_type') ?: $this->mimeTypeMensaje($message) ?: 'application/octet-stream');
        $nombre = $this->nombreArchivoMensaje($message, $mediaId, $mimeType);
        $path = 'whatsapp/'.$fecha->format('Y/m').'/'.Str::uuid().'-'.$nombre;
        Storage::disk('public')->put($path, $downloadResponse->body());

        return [
            'archivo_url' => Storage::disk('public')->url($path),
            'mime_type' => $mimeType,
            'nombre_archivo' => $nombre,
            'tamano_archivo' => (int) (Arr::get($mediaData, 'file_size') ?: strlen($downloadResponse->body())),
        ];
    }

    private function mimeTypeMensaje(array $message): ?string
    {
        $type = Arr::get($message, 'type');

        if (! is_string($type)) {
            return null;
        }

        return Arr::get($message, $type.'.mime_type');
    }

    private function nombreArchivoMensaje(array $message, ?string $mediaId, ?string $mimeType = null): ?string
    {
        $type = Arr::get($message, 'type');
        $nombre = is_string($type) ? Arr::get($message, $type.'.filename') : null;

        if ($nombre) {
            $extension = pathinfo((string) $nombre, PATHINFO_EXTENSION);
            $base = Str::slug(pathinfo((string) $nombre, PATHINFO_FILENAME)) ?: 'archivo';

            return $base.($extension ? '.'.$extension : '');
        }

        if (! $mediaId) {
            return null;
        }

        $extension = $this->extensionDesdeMime($mimeType ?: $this->mimeTypeMensaje($message));

        return $mediaId.($extension ? '.'.$extension : '');
    }

    private function extensionDesdeMime(?string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            default => null,
        };
    }

    private function estadoMensaje(string $estado): string
    {
        return match ($estado) {
            'sent' => 'enviado',
            'delivered' => 'entregado',
            'read' => 'leido',
            'failed', 'deleted' => 'error',
            default => 'pendiente',
        };
    }

    private function fechaMeta(mixed $timestamp): CarbonImmutable
    {
        return is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestamp((int) $timestamp)
            : CarbonImmutable::now();
    }

    private function tokenParaCanal(CanalWhatsapp $canal): ?string
    {
        return $canal->access_token ?: config('services.meta_whatsapp.access_token');
    }

    /** @return array<string, mixed> */
    private function resumenCanal(CanalWhatsapp $canal): array
    {
        return [
            'id' => $canal->id,
            'estado' => $canal->estado,
            'ultimo_error' => $canal->ultimo_error,
            'meta_display_phone_number' => $canal->meta_display_phone_number,
            'meta_verified_name' => $canal->meta_verified_name,
            'meta_quality_rating' => $canal->meta_quality_rating,
            'meta_code_verification_status' => $canal->meta_code_verification_status,
            'meta_verificado_at' => $canal->meta_verificado_at?->format('d/m/Y H:i:s'),
        ];
    }

    private function urlMensajes(CanalWhatsapp $canal): string
    {
        $version = $canal->graph_version ?: config('services.meta_whatsapp.graph_version', 'v21.0');

        return "https://graph.facebook.com/{$version}/{$canal->phone_number_id}/messages";
    }

    private function urlMedia(CanalWhatsapp $canal, string $mediaId): string
    {
        $version = $canal->graph_version ?: config('services.meta_whatsapp.graph_version', 'v21.0');

        return "https://graph.facebook.com/{$version}/{$mediaId}";
    }

    private function urlSubirMedia(CanalWhatsapp $canal): string
    {
        $version = $canal->graph_version ?: config('services.meta_whatsapp.graph_version', 'v21.0');

        return "https://graph.facebook.com/{$version}/{$canal->phone_number_id}/media";
    }

    private function urlTelefono(CanalWhatsapp $canal): string
    {
        $version = $canal->graph_version ?: config('services.meta_whatsapp.graph_version', 'v21.0');

        return "https://graph.facebook.com/{$version}/{$canal->phone_number_id}";
    }
}
