<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoSiat extends Model
{
    protected $table = 'eventos_siat';

    protected $fillable = [
        'metodo', 'parametros', 'respuesta', 'exitoso', 'fecha',
    ];

    protected $casts = [
        'exitoso' => 'boolean',
        'fecha' => 'datetime',
    ];

    public static function registrar(string $metodo, $parametros, $respuesta, bool $exitoso): self
    {
        return static::create([
            'metodo' => $metodo,
            'parametros' => is_string($parametros) ? $parametros : json_encode($parametros, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'respuesta' => is_string($respuesta) ? mb_substr($respuesta, 0, 16000) : json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'exitoso' => $exitoso,
            'fecha' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function parametrosDecodificados(): array
    {
        return $this->decodificarJson($this->parametros);
    }

    /**
     * @return array<string, mixed>
     */
    public function respuestaDecodificada(): array
    {
        return $this->decodificarJson($this->respuesta);
    }

    /**
     * @return array{transaccion: bool|null, codigo_estado: string|null, codigo_recepcion: string|null, descripcion: string}
     */
    public function resumenRespuesta(): array
    {
        $respuesta = $this->respuestaDecodificada();
        $raw = $this->decodificarJson($respuesta['raw'] ?? null);
        $respuestaSin = [];

        foreach ($raw as $clave => $valor) {
            if (str_starts_with((string) $clave, 'Respuesta') && is_array($valor)) {
                $respuestaSin = $valor;
                break;
            }
        }

        $mensajes = $respuestaSin['mensajesList'] ?? $respuesta['mensajesList'] ?? null;
        if (is_array($mensajes) && array_is_list($mensajes)) {
            $mensajes = $mensajes[0] ?? null;
        }

        $transaccion = $respuesta['transaccion'] ?? $respuestaSin['transaccion'] ?? null;
        $descripcion = '';
        foreach ([
            $respuesta['codigoDescripcion'] ?? null,
            $respuestaSin['codigoDescripcion'] ?? null,
            is_array($mensajes) ? ($mensajes['descripcion'] ?? null) : null,
        ] as $candidato) {
            if (is_string($candidato) && trim($candidato) !== '') {
                $descripcion = trim($candidato);
                break;
            }
        }

        if ($descripcion === '') {
            $descripcion = $transaccion === true
                ? 'Operación validada por el SIN.'
                : ($this->exitoso ? 'Proceso local completado correctamente.' : 'La operación no fue aceptada.');
        }

        return [
            'transaccion' => is_bool($transaccion) ? $transaccion : null,
            'codigo_estado' => isset($respuestaSin['codigoEstado']) ? (string) $respuestaSin['codigoEstado'] : null,
            'codigo_recepcion' => $respuesta['codigoRecepcion'] ?? $respuestaSin['codigoRecepcion'] ?? null,
            'descripcion' => $descripcion,
        ];
    }

    public function nombreMetodo(): string
    {
        return match ($this->metodo) {
            'solicitudCuis' => 'Solicitud de CUIS',
            'solicitudCufd' => 'Solicitud de CUFD',
            'generarCuf' => 'Generación de CUF',
            'firmarXml' => 'Preparación y firma del XML',
            'recepcionFactura' => 'Recepción de factura',
            'anulacionFactura' => 'Anulación de factura',
            'reversionAnulacionFactura' => 'Reversión de anulación',
            'recepcionNota' => 'Recepción de nota fiscal',
            'sincronizarLeyendas' => 'Sincronización de leyendas',
            'sincronizarCatalogo' => 'Sincronización de catálogo',
            'registroEventoSignificativo' => 'Registro de contingencia',
            'recepcionPaqueteFactura' => 'Recepción de paquete',
            'validacionRecepcionPaquete' => 'Validación de paquete',
            default => $this->metodo,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificarJson(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if (! is_string($valor) || trim($valor) === '') {
            return [];
        }

        $decodificado = json_decode($valor, true);

        return is_array($decodificado) ? $decodificado : ['detalle' => $valor];
    }
}
