<?php

namespace App\Http\Controllers;

use App\Models\EventoContingencia;
use App\Services\SiatService;
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
        $abierto = EventoContingencia::where('estado', 'abierto')->latest('id')->first();

        return view('contingencias.index', compact('eventos', 'abierto'));
    }

    public function abrir(Request $request, SiatService $siat)
    {
        $data = $request->validate([
            'codigo_evento' => 'required|integer|min:1|max:7',
            'descripcion' => 'nullable|string|max:500',
        ]);

        if (EventoContingencia::where('estado', 'abierto')->exists()) {
            return back()->with('error', 'Ya hay un evento abierto. Ciérralo antes de abrir otro.');
        }

        try {
            $resp = $siat->registrarEvento((int) $data['codigo_evento'], 'inicio');
        } catch (\Throwable $e) {
            return back()->with('error', 'El SIN no aceptó la apertura: '.$e->getMessage());
        }

        EventoContingencia::create([
            'codigo_evento' => $data['codigo_evento'],
            'descripcion' => $data['descripcion']
                ?: (EventoContingencia::CODIGOS[$data['codigo_evento']] ?? 'Evento significativo'),
            'fecha_inicio' => now(),
            'estado' => 'abierto',
            'codigo_recepcion_evento' => $resp['codigoRecepcionEvento'] ?? null,
            'usuario_id' => Auth::id(),
        ]);

        return back()->with('exito', 'Evento registrado ante el SIN');
    }

    public function cerrar(Request $request, EventoContingencia $evento, SiatService $siat)
    {
        if ($evento->estado !== 'abierto') {
            return back()->with('error', 'El evento ya está cerrado.');
        }

        try {
            $siat->registrarEvento($evento->codigo_evento, 'fin');
        } catch (\Throwable $e) {
            return back()->with('error', 'El SIN no aceptó el cierre: '.$e->getMessage());
        }

        $evento->update(['fecha_fin' => now(), 'estado' => 'cerrado']);

        return back()->with('exito', 'Evento cerrado. Ya puedes empaquetar sus facturas.');
    }

    public function empaquetar(EventoContingencia $evento, SiatService $siat)
    {
        if ($evento->estado !== 'cerrado') {
            return back()->with('error', 'Cierra el evento antes de empaquetar.');
        }

        $facturas = $evento->facturas()
            ->where('tipo_emision', 2)
            ->where('en_paquete', false)
            ->whereNotNull('xml_firmado')
            ->get();

        if ($facturas->isEmpty()) {
            return back()->with('error', 'No hay facturas de contingencia pendientes en este evento.');
        }

        $tar = new TarBuilder;
        foreach ($facturas as $f) {
            $tar->agregar($f->numero_factura.'.xml', $f->xml_firmado);
        }
        $binario = $tar->contenidoTarGz();

        try {
            $resp = $siat->recepcionPaquete($binario, $facturas->count());
        } catch (\Throwable $e) {
            return back()->with('error', 'El SIN no aceptó el paquete: '.$e->getMessage());
        }

        $path = 'paquetes/evento-'.$evento->id.'-'.date('Ymd-His').'.tar.gz';
        Storage::disk('local')->put($path, $binario);

        DB::transaction(function () use ($evento, $facturas, $path, $resp) {
            $evento->update([
                'estado' => 'enviado',
                'paquete_path' => $path,
                'codigo_recepcion_paquete' => $resp['codigoRecepcion'] ?? null,
                'estado_paquete' => 'enviado',
            ]);
            foreach ($facturas as $f) {
                $f->update(['en_paquete' => true]);
            }
        });

        return back()->with('exito', 'Paquete enviado. Recepción: '.($resp['codigoRecepcion'] ?? '?'));
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
