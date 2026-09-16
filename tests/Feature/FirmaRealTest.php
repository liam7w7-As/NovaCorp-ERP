<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Services\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifica la firma XMLDSig REAL (electrónica, no simulador) de forma
 * criptográfica: RSA sobre SignedInfo canonicalizado + digest del documento.
 * No requiere credenciales del SIN (certificado autofirmado generado en el test).
 */
class FirmaRealTest extends TestCase
{
    use RefreshDatabase;

    protected string $certPem;

    protected function generarP12(string $password): string
    {
        // En Windows/XAMPP openssl_pkey_new necesita la ruta al openssl.cnf.
        $args = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        foreach (['C:\\xampp\\apache\\conf\\openssl.cnf', 'C:\\xampp\\php\\extras\\openssl\\openssl.cnf'] as $cnf) {
            if (is_file($cnf)) {
                $args['config'] = $cnf;
                break;
            }
        }
        $key = openssl_pkey_new($args);
        $this->assertNotFalse($key);
        $csrOpt = ['digest_alg' => 'sha256'] + (isset($args['config']) ? ['config' => $args['config']] : []);
        $csr = openssl_csr_new(['CN' => 'TEST GISECA'], $key, $csrOpt);
        $cert = openssl_csr_sign($csr, null, $key, 365, $csrOpt);
        $this->assertNotFalse($cert);
        $this->assertNotEmpty(openssl_x509_export($cert, $certPem));
        $this->certPem = $certPem;

        $tmp = tempnam(sys_get_temp_dir(), 'p12').'.p12';
        $this->assertTrue(openssl_pkcs12_export_to_file($cert, $tmp, $key, $password));

        return $tmp;
    }

    public function test_firma_real_es_verificable_criptograficamente(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('openssl no disponible');
        }
        Storage::fake('local');
        $tmp = $this->generarP12('clave123');
        try {
            Storage::disk('local')->put('siat/empresa.p12', file_get_contents($tmp));
            Configuracion::set('siat_certificado_path', 'siat/empresa.p12', 'file');
            Configuracion::set('siat_modalidad', 'electronica', 'text');
            Configuracion::set('siat_modo', 'real', 'text');

            $xml = '<?xml version="1.0" encoding="UTF-8"?><facturaElectronicaCompraVenta><cabecera><nitEmisor>1020304050</nitEmisor></cabecera></facturaElectronicaCompraVenta>';
            $firmado = (new SiatService)->firmarXml($xml, null, 'clave123');

            $this->assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"', $firmado);
            $this->assertStringContainsString('enveloped-signature', $firmado);
            $this->assertStringContainsString('xml-exc-c14n', $firmado);

            $d = new \DOMDocument;
            $d->loadXML($firmado);
            $xp = new \DOMXPath($d);
            $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            $si = $xp->query('//ds:SignedInfo')->item(0);
            $this->assertNotNull($si);

            // 1. La firma RSA abre el SignedInfo canonicalizado con la pública del cert.
            $canonDoc = new \DOMDocument;
            $canonDoc->appendChild($canonDoc->importNode($si, true));
            $canonSi = $canonDoc->C14N(true, false);
            $sigBin = base64_decode($xp->query('//ds:SignatureValue')->item(0)->textContent);
            $pub = openssl_pkey_get_public($this->certPem);
            $this->assertSame(1, openssl_verify($canonSi, $sigBin, $pub, OPENSSL_ALGO_SHA256));

            // 2. El digest corresponde al documento SIN la firma (enveloped).
            $digestXml = $xp->query('//ds:DigestValue')->item(0)->textContent;
            $sinFirma = preg_replace('/<Signature xmlns.*?<\/Signature>/s', '', $firmado);
            $d2 = new \DOMDocument;
            $d2->loadXML($sinFirma);
            $this->assertSame($digestXml, base64_encode(hash('sha256', $d2->C14N(true, false), true)));
        } finally {
            @unlink($tmp);
        }
    }

    public function test_firma_real_falla_con_password_incorrecta(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('openssl no disponible');
        }
        Storage::fake('local');
        $tmp = $this->generarP12('correcta');
        try {
            Storage::disk('local')->put('siat/empresa.p12', file_get_contents($tmp));
            Configuracion::set('siat_certificado_path', 'siat/empresa.p12', 'file');
            Configuracion::set('siat_modalidad', 'electronica', 'text');
            Configuracion::set('siat_modo', 'real', 'text');

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/contraseña incorrecta/');
            (new SiatService)->firmarXml('<facturaElectronicaCompraVenta/>', null, 'otra');
        } finally {
            @unlink($tmp);
        }
    }
}
