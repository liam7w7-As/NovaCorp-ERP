<?php

namespace App\Http\Controllers;

use App\Models\EventoContingencia;
use App\Models\FacturaElectronica;
use App\Services\SiatService;
use App\Services\SucursalContext;
use App\Services\TarBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventoContingenciaController extends Controller
{
    public function index()
    {
        $eventos = EventoContingencia::orderByDesc('id')->paginate(20);
        $sucursalActiva = SucursalContext::sucursal();
        $abierto = EventoContingencia::where('estado', 'abierto')
            ->where(function ($q) use ($sucursalActiva) {
                $q->where('sucursal_id', $sucursalActiva->id)->orWhereNull('sucursal_id');
            })
            ->latest('id')->first();

        return view('contingencias.index', compact('eventos', 'abierto'));
    }

    public function abrir(Request $request, SiatService $siat)
    {
        $data = $request->validate([
            'codigo_evento' => 'required|integer|min:1|max:7',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $sucursal = SucursalContext::sucursal();
        $puntoVenta = SucursalContext::puntoVenta();

        if (EventoContingencia::where('estado', 'abierto')
            ->where(function ($q) use ($sucursal) {
                $q->where('sucursal_id', $sucursal->id)->orWhereNull('sucursal_id');
            })->exists()) {
            return back()->with('error', 'Ya hay un evento abierto para esta sucursal. Ciérralo antes de abrir otro.');
        }

        try {
            $resp = $siat->registrarEvento((int) $data['codigo_evento'], 'inicio', null, [
                'codigoSucursal' => (int) $sucursal->codigo,
                'codigoPuntoVenta' => (int) $puntoVenta->codigo,
                'cuis' => $puntoVenta->cuis,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'El SIN no aceptó la apertura: '.$e->getMessage());
        }

        EventoContingencia::create([
            'codigo_evento' => $data['codigo_evento'],
            'descripcion' => ($data['descripcion'] ?? null)
                ?: (EventoContingencia::CODIGOS[$data['codigo_evento']] ?? 'Evento significativo'),
            'fecha_inicio' => now(),
            'estado' => 'abierto',
            'codigo_recepcion_evento' => $resp['codigoRecepcionEvento'] ?? null,
            'usuario_id' => Auth::id(),
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'codigo_sucursal' => (int) $sucursal->codigo,
            'codigo_punto_venta' => (int) $puntoVenta->codigo,
        ]);

        return back()->with('exito', 'Evento registrado ante el SIN');
    }

    public function cerrar(Request $request, EventoContingencia $evento, SiatService $siat)
    {
        if ($evento->estado !== 'abierto') {
            return back()->with('error', 'El evento ya está cerrado.');
        }

        $evento->loadMissing('sucursal', 'puntoVenta');
        try {
            $siat->registrarEvento($evento->codigo_evento, 'fin', null, [
                'codigoSucursal' => (int) ($evento->sucursal->codigo ?? $evento->codigo_sucursal ?? 0),
                'codigoPuntoVenta' => (int) ($evento->puntoVenta->codigo ?? $evento->codigo_punto_venta ?? 0),
                'cuis' => $evento->puntoVenta?->cuis,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'El SIN no aceptó el cierre: '.$e->getMessage());
        }

        $evento->update(['fecha_fin' => now(), 'estado' => 'cerrado']);

        return back()->with('exito', 'Evento cerrado. Ya puedes empaquetar sus facturas.');
    }

    public function empaquetar(EventoContingencia $evento, SiatService $siat)
    {
        if (! in_array($evento->estado, ['cerrado', 'enviado'], true)) {
            return back()->with('error', 'Cierra el evento antes de empaquetar.');
        }

        // Facturas del evento + huérfanas de contingencia de la misma
        // sucursal (emitidas sin evento abierto), en lotes de 500 (límite SIN).
        $facturas = $evento->facturas()
            ->where('tipo_emision', 2)
            ->where('en_paquete', false)
            ->whereNotNull('xml_firmado')
            ->union(
                FacturaElectronica::where('tipo_emision', 2)
                    ->where('en_paquete', false)
                    ->whereNotNull('xml_firmado')
                    ->whereNull('evento_id')
                    ->where('sucursal_id', $evento->sucursal_id)
            )
            ->limit(TarBuilder::MAX_POR_PAQUETE)
            ->get();

        if ($facturas->isEmpty()) {
            return back()->with('error', 'No hay facturas de contingencia pendientes en este evento.');
        }

        $tar = new TarBuilder;
        foreach ($facturas as $f) {
            $tar->agregar($f->numero_factura.'.xml', $f->xml_firmado);
        }
        $binario = $tar->contenidoTarGz();

        $evento->loadMissing('sucursal', 'puntoVenta');
        try {
            $resp = $siat->recepcionPaquete($binario, $facturas->count(), [
                'codigoSucursal' => (int) ($evento->sucursal->codigo ?? $evento->codigo_sucursal ?? 0),
                'codigoPuntoVenta' => (int) ($evento->puntoVenta->codigo ?? $evento->codigo_punto_venta ?? 0),
                'cuis' => $evento->puntoVenta?->cuis,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'El SIN no aceptó el paquete: '.$e->getMessage());
        }

        $path = 'paquetes/evento-'.$evento->id.'-'.date('Ymd-His').'.tar.gz';
        Storage::disk('local')->put($path, $binario);
        if (hash('sha256', (string) Storage::disk('local')->get($path)) !== hash('sha256', $binario)) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'El paquete se corrompió al guardarse. Intenta de nuevo.');
        }

        DB::transaction(function () use ($evento, $facturas, $path, $resp) {
            $evento->update([
                'estado' => 'enviado',
                'paquete_path' => $path,
                'codigo_recepcion_paquete' => $resp['codigoRecepcion'] ?? null,
                'estado_paquete' => 'enviado',
            ]);
            $ids = $facturas->map->id->all();
            FacturaElectronica::whereIn('id', $ids)
                ->update(['en_paquete' => true, 'evento_id' => $evento->id]);
        });

        $mensaje = 'Paquete enviado ('.$facturas->count().' factura(s)). Recepción: '.($resp['codigoRecepcion'] ?? '?');
        $restantes = FacturaElectronica::where('tipo_emision', 2)
            ->where('en_paquete', false)
            ->where(function ($q) use ($evento) {
                $q->where('evento_id', $evento->id)
                    ->orWhere(function ($q2) use ($evento) {
                        $q2->whereNull('evento_id')->where('sucursal_id', $evento->sucursal_id);
                    });
            })->count();
        if ($restantes > 0) {
            $mensaje .= " Quedan {$restantes} pendiente(s) para el siguiente lote (máx. ".TarBuilder::MAX_POR_PAQUETE.' por paquete).';
        }

        return back()->with('exito', $mensaje);
    }

    public function validar(EventoContingencia $evento, SiatService $siat)
    {
        if (! $evento->codigo_recepcion_paquete) {
            return back()->with('error', 'Este evento aún no tiene paquete enviado.');
        }

        try {
            $resp = $siat->validarPaquete($evento->codigo_recepcion_paquete);
        } catch (\Throwable $e) {
            return back()->with('error', 'Falló la validación: '.$e->getMessage());
        }

        if (! ($resp['transaccion'] ?? false)) {
            $evento->update([
                'estado_paquete' => 'observado',
                'observaciones_sin' => mb_substr((string) ($resp['codigoDescripcion'] ?? ''), 0, 2000),
            ]);

            return back()->with('error', 'Paquete observado: '.($resp['codigoDescripcion'] ?? ''));
        }

        $evento->update(['estado' => 'validado', 'estado_paquete' => 'validado']);

        return back()->with('exito', 'Paquete validado por el SIN');
    }

    public function descargar(EventoContingencia $evento)
    {
        if (! $evento->paquete_path || ! Storage::disk('local')->exists($evento->paquete_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $evento->paquete_path,
            'paquete-evento-'.$evento->id.'.tar.gz'
        );
    }
}
