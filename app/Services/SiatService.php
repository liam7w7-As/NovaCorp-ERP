<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\EventoSiat;
use App\Models\PuntoVenta;
use Illuminate\Support\Facades\Storage;
use SoapClient;
use Throwable;

/**
 * Comunicación con los servicios SOAP del SIN (Facturación en Línea).
 *
 * MODOS:
 * - simulador (por defecto): no toca la red; devuelve respuestas deterministas
 *   con prefijo SIM- y audita cada llamada en eventos_siat. Sirve para probar
 *   todo el flujo (CUIS → CUFD → recepción → anulación) sin credenciales.
 * - real: consume los WSDL oficiales con Token Delegado + certificado .p12.
 *   Requiere NIT, código de sistema, token, CUIS/CUFD vigentes y certificado.
 *
 * Endpoints (RND 102100000011):
 * - Pruebas:    https://pilotosiatservicios.impuestos.gob.bo/v2/{Servicio}?wsdl
 * - Producción: https://siatrest.impuestos.gob.bo/v2/{Servicio}?wsdl
 */
class SiatService
{
    protected function baseUrl(): string
    {
        return SiatConfig::get('siat_ambiente') === 'produccion'
            ? 'https://siatrest.impuestos.gob.bo/v2'
            : 'https://pilotosiatservicios.impuestos.gob.bo/v2';
    }

    protected function cliente(string $servicio): SoapClient
    {
        return new SoapClient($this->baseUrl().'/'.$servicio.'?wsdl', [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 25,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);
    }

    /**
     * Cliente SOAP autenticado: el SIN exige el token en la CABECERA HTTP
     * (`Apikey: TokenApi ...`), no solo en el XML. Se envía en ambas
     * (la del XML es ignorada por el SIN pero inofensiva).
     */
    protected function clienteAutenticado(string $servicio, string $token): SoapClient
    {
        $contexto = stream_context_create([
            'http' => ['header' => 'Apikey: TokenApi '.$token."\r\n"],
        ]);
        $client = new SoapClient($this->baseUrl().'/'.$servicio.'?wsdl', [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 25,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => $contexto,
        ]);
        $client->__setSoapHeaders(new \SoapHeader('https://siat.impuestos.gob.bo/', 'apiKey', 'TokenApi '.$token));

        return $client;
    }

    protected function auditar(string $metodo, $parametros, $respuesta, bool $exitoso): void
    {
        try {
            EventoSiat::registrar($metodo, $parametros, $respuesta, $exitoso);
        } catch (Throwable) {
            // La auditoría nunca debe romper el flujo fiscal
        }
    }

    /**
     * Primera descripción de mensajesList (puede venir objeto único o arreglo).
     */
    protected static function primerMensaje($nodo): string
    {
        $lista = $nodo->mensajesList ?? null;
        if (is_array($lista)) {
            return (string) ($lista[0]->descripcion ?? '');
        }
        if (is_object($lista)) {
            return (string) ($lista->descripcion ?? '');
        }

        return '';
    }

    protected static function vigenciaReal(?string $fechaVigencia, \DateTimeInterface $respaldo): \DateTimeInterface
    {
        if ($fechaVigencia) {
            try {
                return new \DateTimeImmutable($fechaVigencia);
            } catch (Throwable) {
                // cae al respaldo
            }
        }

        return $respaldo;
    }

    /**
     * Mensaje de error enriquecido con el XML SOAP enviado (token recortado),
     * para diagnosticar rechazos del SIN sin acceso al servidor.
     */
    protected function errorSoap($client, Throwable $e): string
    {
        $detalle = $e->getMessage();
        try {
            if ($client instanceof SoapClient) {
                $req = $client->__getLastRequest();
                if ($req) {
                    $req = preg_replace('/(TokenApi\s+)([A-Za-z0-9\-_]{6})[A-Za-z0-9\-_\.]+/', '$1$2***', $req);
                    $detalle .= ' | REQUEST: '.mb_substr((string) $req, 0, 2000);
                }
            }
        } catch (Throwable) {
            // el diagnóstico nunca debe romper el flujo
        }

        return $detalle;
    }

    // ---------------- CUIS ----------------

    public function solicitarCuis(int $codigoSucursal = 0, int $codigoPuntoVenta = 0, ?int $puntoVentaId = null): array
    {
        // Orden según secuencia del XSD (solicitudCuis).
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoModalidad' => SiatConfig::codigoModalidadSin(),
            'codigoPuntoVenta' => $codigoPuntoVenta,
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'codigoSucursal' => $codigoSucursal,
            'nit' => (int) SiatConfig::get('siat_nit'),
        ];

        if (SiatConfig::esSimulador()) {
            $cuis = 'SIM-CUIS-'.strtoupper(substr(md5(json_encode($params).microtime()), 0, 12));
            if ($codigoSucursal === 0 && $codigoPuntoVenta === 0) {
                Configuracion::set('siat_cuis', $cuis, 'text');
            }
            if ($puntoVentaId && ($pv = PuntoVenta::find($puntoVentaId))) {
                $pv->update([
                    'cuis' => $cuis,
                    'cuis_vigencia' => now()->addYear(),
                ]);
            }
            $res = ['transaccion' => true, 'cuis' => $cuis, 'codigoDescripcion' => 'SIMULADOR: CUIS generado localmente', 'simulado' => true];
            $this->auditar('solicitudCuis', $params + ['_modo' => 'simulador'], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            if (! $token) {
                throw new \RuntimeException('Falta el Token Delegado en la configuración SIAT.');
            }
            $client = $this->clienteAutenticado('FacturacionCodigos', $token);
            $resp = $client->__soapCall('cuis', [[
                'SolicitudCuis' => $params,
            ]]);
            $ok = (bool) ($resp->RespuestaCuis->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'cuis' => $resp->RespuestaCuis->codigo ?? null,
                'codigoDescripcion' => self::primerMensaje($resp->RespuestaCuis),
            ];
            if ($ok && $res['cuis']) {
                $vigencia = self::vigenciaReal(
                    isset($resp->RespuestaCuis->fechaVigencia) ? (string) $resp->RespuestaCuis->fechaVigencia : null,
                    now()->addYear()
                );
                if ($codigoSucursal === 0 && $codigoPuntoVenta === 0) {
                    Configuracion::set('siat_cuis', $res['cuis'], 'text');
                }
                if ($puntoVentaId && ($pv = PuntoVenta::find($puntoVentaId))) {
                    $pv->update([
                        'cuis' => $res['cuis'],
                        'cuis_vigencia' => $vigencia,
                    ]);
                }
            }
            $this->auditar('solicitudCuis', $params, json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR), $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('solicitudCuis', $params, $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    // ---------------- CUFD ----------------

    public function solicitarCufd(int $codigoSucursal = 0, int $codigoPuntoVenta = 0, ?string $cuis = null, ?int $puntoVentaId = null): array
    {
        $cuis ??= ($puntoVentaId ? PuntoVenta::find($puntoVentaId)?->cuis : null)
            ?: (string) SiatConfig::get('siat_cuis');

        // Orden según secuencia del XSD (solicitudCufd).
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoModalidad' => SiatConfig::codigoModalidadSin(),
            'codigoPuntoVenta' => $codigoPuntoVenta,
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'codigoSucursal' => $codigoSucursal,
            'cuis' => (string) $cuis,
            'nit' => (int) SiatConfig::get('siat_nit'),
        ];

        if (SiatConfig::esSimulador()) {
            if (! $cuis) {
                throw new \RuntimeException('Solicita primero el CUIS para esta sucursal/punto de venta.');
            }
            $cufd = 'SIM-CUFD-'.strtoupper(substr(md5($cuis.microtime()), 0, 16));
            $vigencia = now()->addDay()->format('Y-m-d\TH:i:s.v');
            if ($codigoSucursal === 0 && $codigoPuntoVenta === 0) {
                Configuracion::set('siat_cufd', $cufd, 'text');
                Configuracion::set('siat_cufd_vigencia', $vigencia, 'text');
            }
            if ($puntoVentaId && ($pv = PuntoVenta::find($puntoVentaId))) {
                $pv->update([
                    'cufd' => $cufd,
                    'codigo_control' => 'SIM-CTRL',
                    'cufd_vigencia' => now()->addDay(),
                ]);
            }
            $res = ['transaccion' => true, 'cufd' => $cufd, 'codigoControl' => 'SIM-CTRL', 'fechaVigencia' => $vigencia,
                'codigoDescripcion' => 'SIMULADOR: CUFD generado localmente', 'simulado' => true];
            $this->auditar('solicitudCufd', $params + ['_modo' => 'simulador'], $res, true);

            return $res;
        }

        try {
            if (str_starts_with((string) $cuis, 'SIM-')) {
                throw new \RuntimeException('El CUIS es de simulador (SIM-). Solicita primero un CUIS real para este punto de venta.');
            }
            $token = SiatConfig::secreto('siat_token');
            if (! $token || ! $cuis) {
                throw new \RuntimeException('Faltan Token Delegado o CUIS vigente.');
            }
            $client = $this->clienteAutenticado('FacturacionCodigos', $token);
            $resp = $client->__soapCall('cufd', [[
                'SolicitudCufd' => $params,
            ]]);
            $ok = (bool) ($resp->RespuestaCufd->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'cufd' => $resp->RespuestaCufd->codigo ?? null,
                'codigoControl' => $resp->RespuestaCufd->codigoControl ?? null,
                'fechaVigencia' => $resp->RespuestaCufd->fechaVigencia ?? null,
                'codigoDescripcion' => self::primerMensaje($resp->RespuestaCufd),
            ];
            if ($ok && $res['cufd']) {
                $vigencia = self::vigenciaReal(
                    $res['fechaVigencia'] ? (string) $res['fechaVigencia'] : null,
                    now()->addDay()
                );
                if ($codigoSucursal === 0 && $codigoPuntoVenta === 0) {
                    Configuracion::set('siat_cufd', $res['cufd'], 'text');
                    Configuracion::set('siat_cufd_vigencia', $vigencia->format('Y-m-d\TH:i:s.v'), 'text');
                }
                if ($puntoVentaId && ($pv = PuntoVenta::find($puntoVentaId))) {
                    $pv->update([
                        'cufd' => $res['cufd'],
                        'codigo_control' => $res['codigoControl'] ?? null,
                        'cufd_vigencia' => $vigencia,
                    ]);
                }
            }
            $this->auditar('solicitudCufd', $params, json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR), $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('solicitudCufd', $params, $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    // ---------------- CUF ----------------

    /**
     * CUF según Anexo Técnico SIN: cadena decimal + dígito módulo 11, luego a hexadecimal.
     * Campos: NIT(13) + fechaHora(17) + sucursal + modalidad + emisión + tipoFactura + docSector + número + puntoVenta.
     */
    public function generarCuf(
        string $nit,
        string $fechaHora, // YmdHis + milisegundos (17 dígitos)
        string $sucursal,
        int $modalidad,
        int $emision, // 1 en línea
        int $tipoFactura, // 1 con derecho a crédito fiscal
        int $docSector, // 1 factura compra-venta
        string $numeroFactura,
        string $puntoVenta,
    ): string {
        if (SiatConfig::esSimulador()) {
            $semilla = implode('|', func_get_args());
            $hash = strtoupper(sha1($semilla.microtime()));
            $cuf = 'SIM'.substr($hash, 0, 37);
            $this->auditar('generarCuf', ['_modo' => 'simulador'] + compact('nit', 'numeroFactura'), ['cuf' => $cuf], true);

            return $cuf;
        }

        $cadena = str_pad($nit, 13, '0', STR_PAD_LEFT)
            .$fechaHora
            .str_pad($sucursal, 4, '0', STR_PAD_LEFT)
            .str_pad((string) $modalidad, 1, '0', STR_PAD_LEFT)
            .str_pad((string) $emision, 1, '0', STR_PAD_LEFT)
            .str_pad((string) $tipoFactura, 1, '0', STR_PAD_LEFT)
            .str_pad((string) $docSector, 2, '0', STR_PAD_LEFT)
            .str_pad($numeroFactura, 10, '0', STR_PAD_LEFT)
            .str_pad($puntoVenta, 4, '0', STR_PAD_LEFT);

        $digito = self::modulo11($cadena);
        $cuf = self::decimalAHex($cadena.$digito);
        $this->auditar('generarCuf', compact('nit', 'numeroFactura'), ['cuf' => $cuf], true);

        return $cuf;
    }

    public static function modulo11(string $cadena): int
    {
        $suma = 0;
        $factor = 2;
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += ((int) $cadena[$i]) * $factor;
            $factor = $factor === 9 ? 2 : $factor + 1;
        }
        $resto = $suma % 11;
        if ($resto === 0) {
            return 0;
        }
        if ($resto === 1) {
            return 1; // criterio SIN para este caso
        }

        return 11 - $resto;
    }

    public static function decimalAHex(string $decimal): string
    {
        $decimal = ltrim($decimal, '0');
        if ($decimal === '') {
            return '0';
        }
        $hex = '';
        while (bccomp($decimal, '0') > 0) {
            $resto = (int) bcmod($decimal, '16');
            $hex = dechex($resto).$hex;
            $decimal = bcdiv($decimal, '16', 0);
        }

        return strtoupper($hex);
    }

    // ---------------- Firma XML ----------------

    /**
     * Firma enveloped XMLDSig (RSA-SHA256) con el .p12.
     * Sólo requerida en modalidad Electrónica; en Computarizada devuelve el XML sin firmar.
     * En simulador incrusta un marcador sin criptografía.
     */
    public function firmarXml(string $xml, ?string $p12Absoluto = null, ?string $password = null): string
    {
        // Computarizada en Línea no usa firma digital
        if (SiatConfig::esComputarizada()) {
            $this->auditar('firmarXml', ['_modalidad' => 'computarizada', 'bytes' => strlen($xml)], ['omitida' => true], true);

            return $xml;
        }

        $etiquetaCierre = '</'.SiatConfig::etiquetaRaizXml().'>';

        if (SiatConfig::esSimulador()) {
            $marcado = preg_replace(
                '/'.preg_quote($etiquetaCierre, '/').'/u',
                '<firmaDigital><modo>SIMULADOR</modo><fecha>'.now()->toIso8601String().'</fecha></firmaDigital>'.$etiquetaCierre,
                $xml, 1
            );
            $this->auditar('firmarXml', ['_modo' => 'simulador', 'bytes' => strlen($xml)], ['bytes' => strlen($marcado ?? $xml)], true);

            return $marcado ?? $xml;
        }

        $p12Absoluto ??= $this->rutaCertificado();
        $password ??= SiatConfig::secreto('siat_cert_password');
        if (! $p12Absoluto || ! is_file($p12Absoluto)) {
            throw new \RuntimeException('Certificado digital .p12 no encontrado.');
        }
        if (! openssl_pkcs12_read(file_get_contents($p12Absoluto), $certs, (string) $password)) {
            throw new \RuntimeException('No se pudo leer el .p12 (¿contraseña incorrecta?).');
        }

        // Enveloped XMLDSig: el digest se calcula sobre el documento SIN la
        // firma (equivale a aplicar la transform enveloped-signature, ya que
        // el nodo <Signature> se inserta después).
        $doc = new \DOMDocument('1.0', 'UTF-8');
        if (! $doc->loadXML($xml)) {
            throw new \RuntimeException('XML inválido para firma.');
        }
        $canon = $doc->C14N(true, false);
        if ($canon === false) {
            throw new \RuntimeException('No se pudo canonicalizar el XML.');
        }
        $digest = base64_encode(hash('sha256', $canon, true));

        $dsig = 'http://www.w3.org/2000/09/xmldsig#';
        $excC14n = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $signedInfo = '<SignedInfo xmlns="'.$dsig.'">'
            .'<CanonicalizationMethod Algorithm="'.$excC14n.'"/>'
            .'<SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>'
            .'<Reference URI="">'
            .'<Transforms>'
            .'<Transform Algorithm="'.$dsig.'enveloped-signature"/>'
            .'<Transform Algorithm="'.$excC14n.'"/>'
            .'</Transforms>'
            .'<DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'
            .'<DigestValue>'.$digest.'</DigestValue>'
            .'</Reference>'
            .'</SignedInfo>';

        // Se firma el SignedInfo YA canonicalizado (Exclusive C14N),
        // igual que lo hará cualquier verificador del SIN.
        $tmp = new \DOMDocument('1.0', 'UTF-8');
        $tmp->loadXML($signedInfo);
        $canonSi = $tmp->C14N(true, false);
        if ($canonSi === false) {
            throw new \RuntimeException('No se pudo canonicalizar el SignedInfo.');
        }
        $ok = openssl_sign($canonSi, $firma, $certs['pkey'], OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new \RuntimeException('Falló la firma RSA del XML.');
        }
        $x509 = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s/', '', $certs['cert']);
        $signature = '<Signature xmlns="'.$dsig.'">'.$signedInfo
            .'<SignatureValue>'.base64_encode($firma).'</SignatureValue>'
            .'<KeyInfo><X509Data><X509Certificate>'.$x509.'</X509Certificate></X509Data></KeyInfo></Signature>';

        $firmado = preg_replace('/'.preg_quote($etiquetaCierre, '/').'/u', $signature.$etiquetaCierre, $xml, 1);
        $this->auditar('firmarXml', ['bytes' => strlen($xml)], ['bytes' => strlen($firmado ?? $xml)], true);

        return $firmado ?? $xml;
    }

    protected function rutaCertificado(): ?string
    {
        $rel = SiatConfig::get('siat_certificado_path');
        if (! $rel) {
            return null;
        }

        // El .p12 se guarda con Storage::disk('local') (storage/app/private/).
        return Storage::disk('local')->path($rel);
    }

    // ---------------- Recepción de factura ----------------

    /**
     * $contexto: identidad fiscal del punto de venta emisor
     * ['codigoSucursal', 'codigoPuntoVenta', 'cufd', 'cuis'].
     * Si se omite, usa los valores globales de SiatConfig (Casa Matriz).
     */
    public function recepcionFactura(string $xmlFirmado, string $cuf, string $numeroFactura, \DateTimeInterface $fechaEmision, int $emision = 1, ?string $cafc = null, array $contexto = []): array
    {
        $hash = hash('sha256', $xmlFirmado);
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoDocumentoSector' => 1,
            'codigoEmision' => $emision,
            'codigoModalidad' => SiatConfig::codigoModalidadSin(),
            'codigoPuntoVenta' => (int) ($contexto['codigoPuntoVenta'] ?? SiatConfig::get('siat_punto_venta', '0')),
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'codigoSucursal' => (int) ($contexto['codigoSucursal'] ?? SiatConfig::get('siat_sucursal', '0')),
            'cufd' => $emision === 2 ? null : (string) ($contexto['cufd'] ?? SiatConfig::get('siat_cufd')),
            'cuis' => (string) ($contexto['cuis'] ?? SiatConfig::get('siat_cuis')),
            'nit' => (int) SiatConfig::get('siat_nit'),
            'tipoFacturaDocumento' => 1,
            'archivo' => base64_encode(gzencode($xmlFirmado)),
            'fechaEnvio' => $fechaEmision->format('Y-m-d\TH:i:s.v'),
            'hashArchivo' => $hash,
            'cafc' => $cafc,
            'codigoControl' => null,
        ];

        if (SiatConfig::esSimulador()) {
            $res = [
                'transaccion' => true,
                'codigoRecepcion' => 'SIM-R-'.strtoupper(substr(md5($cuf.microtime()), 0, 10)),
                'codigoDescripcion' => $emision === 2
                    ? 'SIMULADOR: factura en contingencia recibida (sin valor fiscal)'
                    : 'SIMULADOR: factura recibida y validada (sin valor fiscal)',
                'cuf' => $cuf,
                'numeroFactura' => $numeroFactura,
                'simulado' => true,
            ];
            $this->auditar('recepcionFactura', ['_modo' => 'simulador', 'cuf' => $cuf, 'hash' => $hash, 'emision' => $emision], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $client = $this->clienteAutenticado(SiatConfig::servicioFacturacionWsdl(), (string) $token);
            $resp = $client->__soapCall('recepcionFactura', [[
                'SolicitudServicioRecepcionFactura' => $params,
            ]]);
            $r = $resp->RespuestaServicioFacturacion ?? null;
            $ok = (bool) ($r->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'codigoRecepcion' => $r->codigoRecepcion ?? null,
                'codigoDescripcion' => self::primerMensaje($r),
                'raw' => json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ];
            $this->auditar('recepcionFactura', ['cuf' => $cuf, 'hash' => $hash], $res, $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('recepcionFactura', ['cuf' => $cuf, 'hash' => $hash], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    // ---------------- Anulación ----------------

    public function anulacionFactura(string $cuf, int $codigoMotivo = 1, array $contexto = []): array
    {
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoDocumentoSector' => 1,
            'codigoEmision' => 1,
            'codigoModalidad' => SiatConfig::codigoModalidadSin(),
            'codigoPuntoVenta' => (int) ($contexto['codigoPuntoVenta'] ?? SiatConfig::get('siat_punto_venta', '0')),
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'codigoSucursal' => (int) ($contexto['codigoSucursal'] ?? SiatConfig::get('siat_sucursal', '0')),
            'cufd' => (string) ($contexto['cufd'] ?? SiatConfig::get('siat_cufd')),
            'cuis' => (string) ($contexto['cuis'] ?? SiatConfig::get('siat_cuis')),
            'nit' => (int) SiatConfig::get('siat_nit'),
            'tipoFacturaDocumento' => 1,
            'codigoMotivo' => $codigoMotivo,
            'cuf' => $cuf,
        ];

        if (SiatConfig::esSimulador()) {
            $res = ['transaccion' => true, 'codigoDescripcion' => 'SIMULADOR: factura anulada (sin valor fiscal)', 'simulado' => true];
            $this->auditar('anulacionFactura', ['_modo' => 'simulador', 'cuf' => $cuf], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $client = $this->clienteAutenticado(SiatConfig::servicioFacturacionWsdl(), (string) $token);
            $resp = $client->__soapCall('anulacionFactura', [[
                'SolicitudServicioAnulacionFactura' => $params,
            ]]);
            $r = $resp->RespuestaServicioFacturacion ?? null;
            $ok = (bool) ($r->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'codigoDescripcion' => self::primerMensaje($r),
                'raw' => json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ];
            $this->auditar('anulacionFactura', ['cuf' => $cuf], $res, $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('anulacionFactura', ['cuf' => $cuf], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    // ---------------- Notas débito/crédito ----------------

    /**
     * Registra una nota de débito o crédito asociada a una factura.
     * En modo real el SIN recibe las notas por recepcionFactura con
     * documento sector 24 (XML propio): aún no implementado, se bloquea
     * con mensaje claro en vez de llamar a una operación inexistente.
     */
    public function recepcionNota(string $cufOrigen, string $tipo, float $monto, string $motivo): array
    {
        $params = [
            'cufOrigen' => $cufOrigen,
            'tipo' => $tipo,
            'monto' => $monto,
            'motivo' => $motivo,
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'nit' => (int) SiatConfig::get('siat_nit'),
        ];

        if (SiatConfig::esSimulador()) {
            $res = [
                'transaccion' => true,
                'codigoRecepcion' => 'SIM-N-'.strtoupper(substr(md5($cufOrigen.$tipo.microtime()), 0, 10)),
                'cuf' => 'SIMN'.strtoupper(substr(sha1($cufOrigen.microtime()), 0, 36)),
                'codigoDescripcion' => 'SIMULADOR: nota registrada (sin valor fiscal)',
                'simulado' => true,
            ];
            $this->auditar('recepcionNota', ['_modo' => 'simulador'] + $params, $res, true);

            return $res;
        }

        throw new \RuntimeException(
            'Notas de débito/crédito en modo real aún no soportadas: el SIN las recibe por recepcionFactura con documento sector 24.'
        );
    }

    // ---------------- Catálogos de sincronización ----------------

    /**
     * Lista oficial de leyendas del periodo (FacturacionSincronizacion).
     * En simulador devuelve una lista de ejemplo.
     */
    public function sincronizarLeyendas(int $codigoSucursal = 0, int $codigoPuntoVenta = 0): array
    {
        if (SiatConfig::esSimulador()) {
            $res = [
                'transaccion' => true,
                'leyendas' => [
                    'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos.',
                    'Ley N° 453: El proveedor deberá entregar el producto en las condiciones pactadas.',
                    'Ley N° 453: Tienes derecho a reclamar si el producto no cumple lo ofertado.',
                ],
                'simulado' => true,
            ];
            $this->auditar('sincronizarLeyendas', ['_modo' => 'simulador'], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $cuis = (string) (SiatConfig::get('siat_cuis') ?: '');
            if (! $token || ! $cuis) {
                throw new \RuntimeException('Faltan Token Delegado o CUIS vigente.');
            }
            $client = $this->clienteAutenticado('FacturacionSincronizacion', $token);
            $resp = $client->__soapCall('sincronizarListaLeyendasFactura', [[
                'SolicitudSincronizacion' => [
                    'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
                    'codigoPuntoVenta' => $codigoPuntoVenta,
                    'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
                    'codigoSucursal' => $codigoSucursal,
                    'cuis' => $cuis,
                    'nit' => (int) SiatConfig::get('siat_nit'),
                ],
            ]]);
            $nodo = $resp->RespuestaListaLeyendasFactura ?? null;
            if (! $nodo && isset($resp->sincronizarListaLeyendasFacturaResponse)) {
                $nodo = $resp->sincronizarListaLeyendasFacturaResponse->RespuestaListaLeyendasFactura ?? null;
            }
            $leyendas = [];
            foreach ((array) ($nodo ? ($nodo->listaLeyendas ?? []) : []) as $item) {
                if (! empty($item->descripcionLeyenda)) {
                    $leyendas[] = (string) $item->descripcionLeyenda;
                }
            }
            $res = ['transaccion' => true, 'leyendas' => $leyendas];
            $this->auditar('sincronizarLeyendas', [], ['total' => count($leyendas)], true);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('sincronizarLeyendas', [], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    /**
     * Sincroniza un catálogo paramétrico completo (actividades, productos,
     * documentos, pagos, motivos, eventos, leyendas, unidades). En simulador usa datos de ejemplo;
     * en real consume FacturacionSincronizacion. Retorna [codigo => descripcion].
     */
    public function sincronizarCatalogo(string $tipo, int $codigoSucursal = 0, int $codigoPuntoVenta = 0): array
    {
        if (SiatConfig::esSimulador()) {
            $items = self::catalogoEjemplo($tipo);
            $res = ['transaccion' => true, 'items' => $items, 'simulado' => true];
            $this->auditar('sincronizarCatalogo', ['_modo' => 'simulador', 'tipo' => $tipo], ['total' => count($items)], true);

            return $res;
        }

        $metodos = [
            'actividad' => 'sincronizarActividades',
            'producto' => 'sincronizarListaProductosServicios',
            'documento' => 'sincronizarParametricaTipoDocumentoIdentidad',
            'pago' => 'sincronizarParametricaTipoMetodoPago',
            'motivo' => 'sincronizarParametricaMotivoAnulacion',
            'evento' => 'sincronizarParametricaEventosSignificativos',
            'leyenda' => 'sincronizarListaLeyendasFactura',
            'unidad' => 'sincronizarParametricaUnidadMedida',
        ];
        if (! isset($metodos[$tipo])) {
            throw new \InvalidArgumentException("Catálogo {$tipo} no soportado.");
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $cuis = (string) (SiatConfig::get('siat_cuis') ?: '');
            $client = $this->clienteAutenticado('FacturacionSincronizacion', (string) $token);

            $solicitud = [
                'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
                'codigoPuntoVenta' => $codigoPuntoVenta,
                'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
                'codigoSucursal' => $codigoSucursal,
                'cuis' => $cuis,
                'nit' => (int) SiatConfig::get('siat_nit'),
            ];

            $resp = $client->__soapCall($metodos[$tipo], [[
                'SolicitudSincronizacion' => $solicitud,
            ]]);

            $items = [];
            $respuesta = $resp->RespuestaListaParametricas
                ?? $resp->RespuestaListaActividades
                ?? $resp->RespuestaListaProductos
                ?? $resp->RespuestaListaLeyendas
                ?? $resp;

            $lista = $respuesta->listaCodigos
                ?? $respuesta->listaActividades
                ?? $respuesta->listaLeyendas
                ?? $respuesta->listaProductos
                ?? [];

            foreach ((array) $lista as $item) {
                $codigo = (string) (
                    $item->codigo
                    ?? $item->codigoClasificador
                    ?? $item->codigoProducto
                    ?? $item->codigoCaeb
                    ?? $item->codigoLeyenda
                    ?? ''
                );
                $desc = (string) (
                    $item->descripcion
                    ?? $item->descripcionProducto
                    ?? $item->descripcionLeyenda
                    ?? ''
                );
                if ($codigo !== '' && $desc !== '') {
                    $items[$codigo] = $desc;
                }
            }
            $this->auditar('sincronizarCatalogo', ['tipo' => $tipo], ['total' => count($items)], true);

            return ['transaccion' => true, 'items' => $items];
        } catch (Throwable $e) {
            $this->auditar('sincronizarCatalogo', ['tipo' => $tipo], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    /**
     * Datos oficiales de ejemplo para modo simulador (subconjunto realista oficial SIN Bolivia).
     */
    public static function catalogoEjemplo(string $tipo): array
    {
        return match ($tipo) {
            'actividad' => [
                '474100' => 'Venta al por menor de repuestos para vehículos automotores',
                '453000' => 'Venta de partes, piezas y accesorios para vehículos automotores',
                '471100' => 'Venta al por menor en comercios no especializados',
                '475200' => 'Venta al por menor de artículos de ferretería, pinturas y productos de vidrio',
                '465900' => 'Venta al por mayor de otra maquinaria y equipo',
            ],
            'producto' => [
                '99100' => 'Productos no especificados / servicios varios',
                '32110' => 'Filtros de aceite y combustible para motores',
                '27110' => 'Aceites lubricantes y grasas para motores',
                '38110' => 'Neumáticos, llantas y cámaras de caucho',
                '42120' => 'Baterías y acumuladores eléctricos',
                '43210' => 'Herramientas manuales de uso mecánico y ferretería',
                '44110' => 'Pinturas, esmaltes, barnices y disolventes',
                '45200' => 'Pastillas, zapatas y discos de freno',
                '46100' => 'Repuestos y componentes eléctricos para automotores',
                '51100' => 'Tornillos, tuercas, pernos y remaches metálicos',
                '52300' => 'Cables, alambres y conductores eléctricos',
                '62010' => 'Servicios de mantenimiento y reparación automotriz/industrial',
            ],
            'documento' => [
                '1' => 'Cédula de identidad (CI)',
                '2' => 'Cédula de identidad de extranjero (CEX)',
                '3' => 'Pasaporte',
                '4' => 'Otro documento de identidad',
                '5' => 'Número de Identificación Tributaria (NIT)',
            ],
            'pago' => [
                '1' => 'Efectivo',
                '2' => 'Tarjeta de débito/crédito',
                '3' => 'Cheque',
                '4' => 'Vales / Cupones',
                '5' => 'Otros (Transferencia bancaria / QR)',
                '6' => 'Pago posterior (Crédito)',
            ],
            'motivo' => [
                '1' => 'Factura mal emitida',
                '2' => 'Datos de emisión incorrectos',
                '3' => 'Devolución total o parcial',
                '4' => 'Desistimiento de la operación',
            ],
            'evento' => [
                '1' => 'Corte del servicio de internet',
                '2' => 'Inaccesibilidad al servicio web de la AT (SIN)',
                '3' => 'Corte del suministro de energía eléctrica',
                '4' => 'Falla del sistema informático de facturación',
                '5' => 'Virus informático o ataque bloqueante',
                '6' => 'Cambio de infraestructura o mantenimiento',
                '7' => 'Otro evento significativo autorizado',
            ],
            'leyenda' => [
                '1' => 'Ley N° 453: Tienes derecho a recibir un trato equitativo y no discriminatorio en el comercio.',
                '2' => 'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos.',
                '3' => 'Ley N° 453: Los productos deben reunir las condiciones de inocuidad y calidad para su consumo o uso.',
                '4' => 'Ley N° 453: El proveedor debe responder por el saneamiento de evicción y los vicios ocultos de los bienes.',
                '5' => 'Este documento es la representación gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea.',
            ],
            'unidad' => [
                '58' => 'UNIDAD (SERVICIOS)',
                '1' => 'BOBINAS',
                '2' => 'BALDE',
                '3' => 'BARRILES',
                '4' => 'BOLSA',
                '5' => 'BOTELLAS',
                '6' => 'CAJA',
                '7' => 'CARTON',
                '10' => 'DOCENA',
                '18' => 'JUEGO',
                '22' => 'KILOGRAMO',
                '23' => 'KILOMETRO',
                '24' => 'LITRO',
                '25' => 'METRO',
                '27' => 'METRO CUADRADO',
                '28' => 'METRO CUBICO',
                '31' => 'PAQUETE',
                '32' => 'PAR',
                '34' => 'PIEZA',
                '35' => 'PLIEGO',
                '40' => 'ROLLO',
                '47' => 'TAMBOR',
                '50' => 'TONELADA',
            ],
            default => [],
        };
    }

    // ---------------- Eventos significativos ----------------

    /**
     * Registra inicio/fin de evento significativo ante el SIN.
     * $fase: 'inicio' | 'fin'. Códigos 1-7 según SIN.
     * $contexto: ['codigoSucursal', 'codigoPuntoVenta', 'cuis'] del PV afectado.
     */
    public function registrarEvento(int $codigoEvento, string $fase, ?string $fechaHora = null, array $contexto = []): array
    {
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'nit' => (int) SiatConfig::get('siat_nit'),
            'codigoSucursal' => (int) ($contexto['codigoSucursal'] ?? SiatConfig::get('siat_sucursal', '0')),
            'codigoPuntoVenta' => (int) ($contexto['codigoPuntoVenta'] ?? SiatConfig::get('siat_punto_venta', '0')),
            'cuis' => (string) ($contexto['cuis'] ?? SiatConfig::get('siat_cuis')),
            'codigoMotivoEvento' => $codigoEvento,
            'fechaHoraInicioEvento' => $fase === 'inicio'
                ? ($fechaHora ?? now()->format('Y-m-d\TH:i:s.v'))
                : null,
            'fechaHoraFinEvento' => $fase === 'fin'
                ? ($fechaHora ?? now()->format('Y-m-d\TH:i:s.v'))
                : null,
        ];

        if (SiatConfig::esSimulador()) {
            $res = [
                'transaccion' => true,
                'codigoRecepcionEvento' => 'SIM-EV-'.strtoupper(substr(md5($codigoEvento.$fase.microtime()), 0, 10)),
                'codigoDescripcion' => "SIMULADOR: {$fase} de evento {$codigoEvento} registrado (sin valor fiscal)",
                'simulado' => true,
            ];
            $this->auditar('registroEventoSignificativo', ['_modo' => 'simulador'] + $params, $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $client = $this->clienteAutenticado('FacturacionOperaciones', (string) $token);
            $resp = $client->__soapCall('registroEventoSignificativo', [[
                'SolicitudEventoSignificativo' => $params,
            ]]);
            $r = $resp->RespuestaListaEventos ?? $resp;
            $ok = (bool) ($r->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'codigoRecepcionEvento' => $r->codigoRecepcionEvento ?? null,
                'codigoDescripcion' => self::primerMensaje($r),
                'raw' => json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ];
            $this->auditar('registroEventoSignificativo', ['codigoEvento' => $codigoEvento, 'fase' => $fase], $res, $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('registroEventoSignificativo', ['codigoEvento' => $codigoEvento, 'fase' => $fase], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    // ---------------- Paquetes de contingencia ----------------

    public function recepcionPaquete(string $tarGzBinario, int $cantidadFacturas, array $contexto = []): array
    {
        $hash = hash('sha256', $tarGzBinario);
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'nit' => (int) SiatConfig::get('siat_nit'),
            'codigoSucursal' => (int) ($contexto['codigoSucursal'] ?? SiatConfig::get('siat_sucursal', '0')),
            'codigoPuntoVenta' => (int) ($contexto['codigoPuntoVenta'] ?? SiatConfig::get('siat_punto_venta', '0')),
            'cuis' => (string) ($contexto['cuis'] ?? SiatConfig::get('siat_cuis')),
            'cantidadFacturas' => $cantidadFacturas,
            'hashArchivo' => $hash,
        ];

        if (SiatConfig::esSimulador()) {
            $res = [
                'transaccion' => true,
                'codigoRecepcion' => 'SIM-PQ-'.strtoupper(substr(md5($hash.microtime()), 0, 10)),
                'codigoDescripcion' => "SIMULADOR: paquete de {$cantidadFacturas} factura(s) recibido (sin valor fiscal)",
                'simulado' => true,
            ];
            $this->auditar('recepcionPaqueteFactura', ['_modo' => 'simulador', 'cantidad' => $cantidadFacturas, 'hash' => $hash], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $client = $this->clienteAutenticado(SiatConfig::servicioFacturacionWsdl(), (string) $token);
            $resp = $client->__soapCall('recepcionPaqueteFactura', [[
                'SolicitudServicioRecepcionPaquete' => $params + [
                    'archivo' => base64_encode($tarGzBinario),
                ],
            ]]);
            $r = $resp->RespuestaServicioFacturacion ?? null;
            $ok = (bool) ($r->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'codigoRecepcion' => $r->codigoRecepcion ?? null,
                'codigoDescripcion' => self::primerMensaje($r),
                'raw' => json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ];
            $this->auditar('recepcionPaqueteFactura', ['cantidad' => $cantidadFacturas, 'hash' => $hash], $res, $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('recepcionPaqueteFactura', ['cantidad' => $cantidadFacturas, 'hash' => $hash], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    public function validarPaquete(string $codigoRecepcion): array
    {
        if (SiatConfig::esSimulador()) {
            $res = [
                'transaccion' => true,
                'estado' => 'VALIDADO',
                'codigoDescripcion' => 'SIMULADOR: paquete validado (sin valor fiscal)',
                'simulado' => true,
            ];
            $this->auditar('validacionRecepcionPaquete', ['_modo' => 'simulador', 'codigoRecepcion' => $codigoRecepcion], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $client = $this->clienteAutenticado(SiatConfig::servicioFacturacionWsdl(), (string) $token);
            $resp = $client->__soapCall('validacionRecepcionPaqueteFactura', [[
                'SolicitudServicioValidacionPaquete' => [
                    'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
                    'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
                    'nit' => (int) SiatConfig::get('siat_nit'),
                    'codigoRecepcion' => $codigoRecepcion,
                ],
            ]]);
            $r = $resp->RespuestaServicioFacturacion ?? null;
            $ok = (bool) ($r->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'codigoDescripcion' => self::primerMensaje($r),
                'raw' => json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ];
            $this->auditar('validacionRecepcionPaquete', ['codigoRecepcion' => $codigoRecepcion], $res, $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('validacionRecepcionPaquete', ['codigoRecepcion' => $codigoRecepcion], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    /**
     * Revierte una anulación dentro del plazo permitido por el SIN.
     */
    public function reversionAnulacion(string $cuf): array
    {
        $params = [
            'codigoAmbiente' => SiatConfig::codigoAmbienteSin(),
            'codigoDocumentoSector' => 1,
            'codigoSistema' => (string) SiatConfig::get('siat_codigo_sistema'),
            'nit' => (int) SiatConfig::get('siat_nit'),
            'cuf' => $cuf,
        ];

        if (SiatConfig::esSimulador()) {
            $res = ['transaccion' => true, 'codigoDescripcion' => 'SIMULADOR: anulación revertida (sin valor fiscal)', 'simulado' => true];
            $this->auditar('reversionAnulacionFactura', ['_modo' => 'simulador', 'cuf' => $cuf], $res, true);

            return $res;
        }

        try {
            $token = SiatConfig::secreto('siat_token');
            $client = $this->clienteAutenticado(SiatConfig::servicioFacturacionWsdl(), (string) $token);
            $resp = $client->__soapCall('reversionAnulacionFactura', [[
                'SolicitudServicioReversionAnulacion' => $params,
            ]]);
            $r = $resp->RespuestaServicioFacturacion ?? null;
            $ok = (bool) ($r->transaccion ?? false);
            $res = [
                'transaccion' => $ok,
                'codigoDescripcion' => self::primerMensaje($r),
                'raw' => json_encode($resp, JSON_PARTIAL_OUTPUT_ON_ERROR),
            ];
            $this->auditar('reversionAnulacionFactura', ['cuf' => $cuf], $res, $ok);

            return $res;
        } catch (Throwable $e) {
            $this->auditar('reversionAnulacionFactura', ['cuf' => $cuf], $this->errorSoap($client ?? null, $e), false);
            throw $e;
        }
    }

    // ---------------- Probar conexión ----------------
    public function probarConexion(): array
    {
        if (SiatConfig::esSimulador()) {
            return ['ok' => true, 'mensaje' => 'Modo SIMULADOR activo: no se llamó al SIN. Cambia a modo REAL con credenciales para probar la conexión verdadera.'];
        }

        try {
            $r = $this->solicitarCuis();

            return ['ok' => (bool) ($r['transaccion'] ?? false), 'mensaje' => $r['codigoDescripcion'] ?? 'Sin respuesta'];
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }
}
