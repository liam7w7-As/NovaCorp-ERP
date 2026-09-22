<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Services\SiatConfig;
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que ambas modalidades (computarizada / electrónica) se comporten
 * correctamente en SiatConfig y SiatService.
 */
class ModalidadFacturacionTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ SiatConfig

    public function test_default_modalidad_es_computarizada(): void
    {
        $this->assertSame('computarizada', SiatConfig::DEFAULTS['siat_modalidad']);
    }

    public function test_es_computarizada_cuando_no_hay_configuracion(): void
    {
        // Sin ninguna fila en la BD → usa DEFAULTS
        $this->assertTrue(SiatConfig::esComputarizada());
        $this->assertFalse(SiatConfig::esElectronica());
    }

    public function test_es_electronica_cuando_se_guarda_electronica(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');

        $this->assertFalse(SiatConfig::esComputarizada());
        $this->assertTrue(SiatConfig::esElectronica());
    }

    public function test_codigo_modalidad_computarizada_es_2(): void
    {
        Configuracion::set('siat_modalidad', 'computarizada', 'text');

        $this->assertSame(2, SiatConfig::codigoModalidadSin());
    }

    public function test_codigo_modalidad_electronica_es_1(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');

        $this->assertSame(1, SiatConfig::codigoModalidadSin());
    }

    public function test_etiqueta_raiz_xml_computarizada(): void
    {
        Configuracion::set('siat_modalidad', 'computarizada', 'text');

        $this->assertSame('facturaComputarizadaCompraVenta', SiatConfig::etiquetaRaizXml());
    }

    public function test_etiqueta_raiz_xml_electronica(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');

        $this->assertSame('facturaElectronicaCompraVenta', SiatConfig::etiquetaRaizXml());
    }

    public function test_servicio_wsdl_computarizada(): void
    {
        Configuracion::set('siat_modalidad', 'computarizada', 'text');

        $this->assertSame('ServicioFacturacionCompraVenta', SiatConfig::servicioFacturacionWsdl());
    }

    public function test_servicio_wsdl_electronica(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');

        $this->assertSame('ServicioFacturacionCompraVenta', SiatConfig::servicioFacturacionWsdl());
    }

    // ------------------------------------------------------------------ SiatService::firmarXml

    public function test_firmar_xml_computarizada_devuelve_xml_sin_firma(): void
    {
        Configuracion::set('siat_modalidad', 'computarizada', 'text');
        // En modo real el Configuracion::get('siat_modo') devolverá null → fallback 'simulador'
        // pero en computarizada el check esComputarizada() debe cortocircuitar antes del bloque firma

        $xml = '<?xml version="1.0" encoding="UTF-8"?><facturaComputarizadaCompraVenta><cabecera/></facturaComputarizadaCompraVenta>';
        $service = new SiatService;

        $resultado = $service->firmarXml($xml);

        $this->assertSame($xml, $resultado, 'Computarizada no debe modificar el XML (sin firma).');
        $this->assertStringNotContainsString('<firmaDigital>', $resultado);
        $this->assertStringNotContainsString('<Signature', $resultado);
    }

    public function test_firmar_xml_electronica_simulador_agrega_marcador(): void
    {
        Configuracion::set('siat_modalidad', 'electronica', 'text');
        Configuracion::set('siat_modo', 'simulador', 'text');

        $xml = '<?xml version="1.0" encoding="UTF-8"?><facturaElectronicaCompraVenta><cabecera/></facturaElectronicaCompraVenta>';
        $service = new SiatService;

        $resultado = $service->firmarXml($xml);

        $this->assertStringContainsString('<firmaDigital>', $resultado, 'Simulador electrónica debe incrustar marcador de firma.');
        $this->assertStringContainsString('<modo>SIMULADOR</modo>', $resultado);
        $this->assertStringContainsString('</facturaElectronicaCompraVenta>', $resultado);
    }

    public function test_archivo_de_recepcion_es_gzip_binario_y_hash_del_comprimido(): void
    {
        $service = new class extends SiatService
        {
            /**
             * @return array{archivo: string, hashArchivo: string}
             */
            public function preparar(string $xml): array
            {
                return $this->prepararArchivoRecepcion($xml);
            }
        };
        $xml = '<?xml version="1.0"?><facturaComputarizadaCompraVenta/>';

        $resultado = $service->preparar($xml);

        $this->assertSame($xml, gzdecode($resultado['archivo']));
        $this->assertSame(hash('sha256', $resultado['archivo']), $resultado['hashArchivo']);
        $this->assertNotSame(base64_encode($resultado['archivo']), $resultado['archivo']);
        $this->assertSame("\x1f\x8b", substr($resultado['archivo'], 0, 2));
    }
}
