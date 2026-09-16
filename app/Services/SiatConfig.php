<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Crypt;

/**
 * Lectura/escritura de la configuración SIAT sobre la tabla configuraciones (Fase 5).
 * Secretos (contraseña cert, token) se guardan encriptados con Crypt.
 */
class SiatConfig
{
    public const DEFAULTS = [
        'siat_modo' => 'simulador', // simulador | real
        'siat_ambiente' => 'pruebas', // pruebas | produccion
        'siat_modalidad' => 'computarizada', // computarizada | electronica
        'siat_sucursal' => '0',
        'siat_punto_venta' => '0',
    ];

    public static function get(string $clave, $default = null)
    {
        if (array_key_exists($clave, self::DEFAULTS) && $default === null) {
            $default = self::DEFAULTS[$clave];
        }

        return Configuracion::get($clave, $default);
    }

    public static function secreto(string $clave): ?string
    {
        $valor = Configuracion::get($clave);
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            return Crypt::decryptString($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function guardar(array $datos): void
    {
        foreach (['siat_nit', 'siat_razon_social', 'siat_modo', 'siat_ambiente', 'siat_modalidad',
            'siat_codigo_sistema', 'siat_sucursal', 'siat_punto_venta', 'siat_telefono',
            'siat_direccion', 'siat_ciudad', 'siat_cafc'] as $k) {
            if (array_key_exists($k, $datos)) {
                Configuracion::set($k, $datos[$k], 'text');
            }
        }
        foreach (['siat_cert_password', 'siat_token'] as $k) {
            if (array_key_exists($k, $datos) && $datos[$k] !== null && $datos[$k] !== '') {
                Configuracion::set($k, Crypt::encryptString($datos[$k]), 'secret');
            }
        }
        if (array_key_exists('siat_certificado_path', $datos)) {
            Configuracion::set('siat_certificado_path', $datos['siat_certificado_path'], 'file');
        }
    }

    public static function esSimulador(): bool
    {
        return self::get('siat_modo', 'simulador') !== 'real';
    }

    /**
     * Modalidad Computarizada en Línea (código SIN = 2).
     * No requiere firma digital ni certificado .p12.
     */
    public static function esComputarizada(): bool
    {
        return self::codigoModalidadSin() === 2;
    }

    /**
     * Modalidad Electrónica en Línea (código SIN = 1).
     * Requiere firma XMLDSig con certificado .p12.
     */
    public static function esElectronica(): bool
    {
        return self::codigoModalidadSin() === 1;
    }

    /**
     * Nombre del servicio SOAP de emisión según modalidad activa.
     * - Computarizada: ServicioFacturacionComputarizada
     * - Electrónica:   ServicioFacturacionCompraVenta
     */
    public static function servicioFacturacionWsdl(): string
    {
        return self::esComputarizada()
            ? 'ServicioFacturacionComputarizada'
            : 'ServicioFacturacionCompraVenta';
    }

    public static function todo(): array
    {
        return [
            'modo' => self::get('siat_modo'),
            'ambiente' => self::get('siat_ambiente'),
            'modalidad' => self::get('siat_modalidad'),
            'nit' => self::get('siat_nit'),
            'razon_social' => self::get('siat_razon_social'),
            'codigo_sistema' => self::get('siat_codigo_sistema'),
            'sucursal' => self::get('siat_sucursal'),
            'punto_venta' => self::get('siat_punto_venta'),
            'telefono' => self::get('siat_telefono'),
            'direccion' => self::get('siat_direccion'),
            'ciudad' => self::get('siat_ciudad'),
            'certificado_path' => self::get('siat_certificado_path'),
            'cafc' => self::get('siat_cafc'),
            'leyendas' => json_decode((string) self::get('siat_leyendas', '[]'), true) ?: [],
            'tiene_password' => (bool) self::secreto('siat_cert_password'),
            'tiene_token' => (bool) self::secreto('siat_token'),
            'cuis' => self::get('siat_cuis'),
            'cufd' => self::get('siat_cufd'),
            'cufd_vigencia' => self::get('siat_cufd_vigencia'),
        ];
    }

    public static function codigoModalidadSin(): int
    {
        return self::get('siat_modalidad') === 'computarizada' ? 2 : 1;
    }

    /**
     * Etiqueta raíz del documento XML fiscal según modalidad.
     */
    public static function etiquetaRaizXml(): string
    {
        return self::esComputarizada()
            ? 'facturaComputarizadaCompraVenta'
            : 'facturaElectronicaCompraVenta';
    }

    public static function codigoAmbienteSin(): int
    {
        return self::get('siat_ambiente') === 'produccion' ? 1 : 2;
    }
}
