<?php

namespace Tests\Feature;

use App\Models\EventoContingencia;
use App\Models\FacturaElectronica;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\SucursalContext;
use App\Services\TarBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContingenciaPaqueteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@giseca.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('secret123'), 'rol' => 'admin', 'activo' => true]
        );
        Storage::fake('local');
    }

    protected function crearPv(Sucursal $sucursal, int $codigo = 1): PuntoVenta
    {
        return PuntoVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja '.$codigo,
            'tipo_punto_venta' => 'Fijo', 'codigo' => $codigo,
            'cuis' => 'CUIS-'.$codigo, 'cuis_vigencia' => now()->addMonths(6),
            'cufd' => 'CUFD-'.$codigo, 'cufd_vigencia' => now()->addDay(),
            'activo' => true,
        ]);
    }

    protected function activarContexto(Sucursal $sucursal, PuntoVenta $pv): void
    {
        session([
            SucursalContext::SESSION_SUCURSAL_KEY => $sucursal->id,
            SucursalContext::SESSION_POS_KEY => $pv->id,
        ]);
    }

    protected function crearFacturaContingencia(Sucursal $sucursal, ?int $eventoId, string $numero): FacturaElectronica
    {
        return FacturaElectronica::create([
            'sucursal_id' => $sucursal->id,
            'codigo_sucursal' => (int) $sucursal->codigo,
            'codigo_punto_venta' => 1,
            'cuf' => 'CUF-'.$numero,
            'numero_factura' => $numero,
            'estado' => 'emitida',
            'fecha_emision' => now(),
            'xml_firmado' => '<?xml version="1.0"?><f>'.$numero.'</f>',
            'tipo_emision' => 2,
            'en_paquete' => false,
            'evento_id' => $eventoId,
            'usuario_id' => $this->admin->id,
        ]);
    }

    public function test_abrir_registra_sucursal_y_pv_y_es_por_sucursal(): void
    {
        $sucA = Sucursal::create(['nombre' => 'A', 'codigo' => 1, 'activa' => true]);
        $pvA = $this->crearPv($sucA);
        $sucB = Sucursal::create(['nombre' => 'B', 'codigo' => 2, 'activa' => true]);
        $pvB = $this->crearPv($sucB);

        $this->activarContexto($sucA, $pvA);
        $this->actingAs($this->admin)
            ->post(route('contingencias.abrir'), ['codigo_evento' => 1])
            ->assertSessionHas('exito');

        $evento = EventoContingencia::latest('id')->firstOrFail();
        $this->assertSame($sucA->id, $evento->sucursal_id);
        $this->assertSame($pvA->id, $evento->punto_venta_id);
        $this->assertSame(1, $evento->codigo_sucursal);

        // Segunda apertura en la misma sucursal se bloquea…
        $this->actingAs($this->admin)
            ->post(route('contingencias.abrir'), ['codigo_evento' => 2])
            ->assertSessionHas('error');

        // …pero otra sucursal sí puede abrir la suya.
        $this->activarContexto($sucB, $pvB);
        $this->actingAs($this->admin)
            ->post(route('contingencias.abrir'), ['codigo_evento' => 2])
            ->assertSessionHas('exito');
        $this->assertSame(2, EventoContingencia::where('estado', 'abierto')->count());
    }

    public function test_empaquetar_incluye_huerfanas_de_la_sucursal(): void
    {
        $suc = Sucursal::create(['nombre' => 'A', 'codigo' => 1, 'activa' => true]);
        $otra = Sucursal::create(['nombre' => 'B', 'codigo' => 2, 'activa' => true]);
        $evento = EventoContingencia::create([
            'codigo_evento' => 1, 'descripcion' => 'Corte', 'fecha_inicio' => now(),
            'estado' => 'cerrado', 'sucursal_id' => $suc->id,
            'codigo_sucursal' => 1, 'codigo_punto_venta' => 1, 'usuario_id' => $this->admin->id,
        ]);

        $f1 = $this->crearFacturaContingencia($suc, $evento->id, 'FAC-H-001');
        $huerfana = $this->crearFacturaContingencia($suc, null, 'FAC-H-002');
        $ajena = $this->crearFacturaContingencia($otra, null, 'FAC-H-003');

        $this->actingAs($this->admin)
            ->post(route('contingencias.empaquetar', $evento))
            ->assertSessionHas('exito');

        $this->assertTrue($f1->fresh()->en_paquete);
        $this->assertTrue($huerfana->fresh()->en_paquete);
        $this->assertSame($evento->id, $huerfana->fresh()->evento_id);
        $this->assertFalse($ajena->fresh()->en_paquete);
        $this->assertSame('enviado', $evento->fresh()->estado);
        $this->assertStringStartsWith('SIM-PQ-', $evento->fresh()->codigo_recepcion_paquete);
        Storage::disk('local')->assertExists($evento->fresh()->paquete_path);
    }

    public function test_tarbuilder_soporta_nombres_largos_y_modo_0644(): void
    {
        $largo = 'paquetes-contingencia-sucursal-12-punto-venta-34-emision-fuera-de-linea/FAC-S12-P34-000123-venta-cliente-corporativo.xml';
        $this->assertGreaterThan(100, strlen($largo));

        $tar = new TarBuilder;
        $tar->agregar($largo, '<xml>contenido</xml>');
        $bin = $tar->contenidoTar();

        // Header de 512: name(0:100) mode(100:8) size(124:12) prefix(345:155)
        $this->assertSame(0, strlen($bin) % 512);
        $nombre = rtrim(substr($bin, 0, 100), "\0");
        $modo = rtrim(substr($bin, 100, 8), "\0 ");
        $tam = octdec(trim(substr($bin, 124, 12)));
        $prefijo = rtrim(substr($bin, 345, 155), "\0");

        $this->assertSame('0000644', $modo);
        $this->assertSame(strlen('<xml>contenido</xml>'), $tam);
        $this->assertSame($largo, $prefijo.'/'.$nombre);
        $this->assertSame('<xml>contenido</xml>', substr($bin, 512, $tam));

        // gzip redondo
        $this->assertSame($bin, gzdecode($tar->contenidoTarGz()));
    }
}
