<?php

namespace App\Http\Controllers;

use App\Mail\FacturaCorreo;
use App\Models\Auditoria;
use App\Models\Configuracion;
use App\Models\FacturaElectronica;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\FacturaService;
use App\Services\SucursalContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $query = FacturaElectronica::with(['venta', 'sucursal', 'puntoVenta'])->orderByDesc('id');
        $sucursalId = $this->sucursalPermitidaParaLectura($request);
        $this->aplicarSucursalVisible($query, $sucursalId);

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }
        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('numero_factura', 'like', "%{$q}%")
                    ->orWhere('cuf', 'like', "%{$q}%")
                    ->orWhere('codigo_recepcion', 'like', "%{$q}%");
            });
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_emision', '>=', $request->get('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->get('hasta'));
        }

        $facturas = $query->paginate(20)->withQueryString();

        $resumen = [];
        foreach (FacturaElectronica::ESTADOS as $e) {
            $resumenQuery = FacturaElectronica::where('estado', $e);
            $this->aplicarSucursalVisible($resumenQuery, $sucursalId);
            $resumen[$e] = $resumenQuery->count();
        }

        return view('facturas.index', [
            'facturas' => $facturas,
            'resumen' => $resumen,
            'estado' => $request->get('estado', ''),
            'sucursal_id' => $sucursalId ?: $request->get('sucursal_id', ''),
            'sucursales' => $this->sucursalesVisibles($request),
            'q' => $request->get('q', ''),
            'desde' => $request->get('desde', ''),
            'hasta' => $request->get('hasta', ''),
        ]);
    }

    public function show(FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $factura->load('venta.detalles', 'usuario', 'notas', 'sucursal', 'puntoVenta');

        return view('facturas.show', compact('factura'));
    }

    /**
     * Emite factura electrónica para una venta existente (manual, nunca automático).
     */
    public function emitir(Request $request, Venta $venta, FacturaService $facturas)
    {
        SucursalContext::autorizaSucursal($venta->sucursal_id);
        $data = $request->validate([
            'contingencia' => 'nullable|boolean',
        ]);

        try {
            $factura = $facturas->emitirDesdeVenta($venta, null, [
                'contingencia' => (bool) ($data['contingencia'] ?? false),
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('facturas.show', $factura)
            ->with('exito', "Factura {$factura->numero_factura} emitida. Recepción: {$factura->codigo_recepcion}".$this->enviarCorreo($factura, 'emitida'));
    }

    /**
     * Envía la factura por correo si el cliente tiene email. Nunca rompe el flujo.
     * Retorna sufijo informativo para el mensaje flash.
     */
    protected function enviarCorreo(FacturaElectronica $factura, string $motivo): string
    {
        $correo = $factura->venta?->cliente?->correo ?? null;
        if (! $correo) {
            return '';
        }
        try {
            Mail::to($correo)->send(new FacturaCorreo($factura, $motivo));

            return ' Correo enviado a '.$correo.'.';
        } catch (\Throwable) {
            return ' (correo no enviado: sin servicio SMTP configurado).';
        }
    }

    public function reenviarCorreo(Request $request, FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $request->validate([
            'email' => 'nullable|email',
        ]);

        if ($request->filled('email') && $factura->venta?->cliente) {
            $factura->venta->cliente->update(['correo' => $request->input('email')]);
        }

        $destino = $request->input('email') ?: ($factura->venta?->cliente?->correo ?? null);
        if (! $destino) {
            return back()->with('error', 'El cliente no tiene correo electrónico registrado para el envío.');
        }

        try {
            Mail::to($destino)->send(new FacturaCorreo($factura, $factura->estado === 'anulada' ? 'anulada' : 'emitida'));

            return back()->with('exito', "Factura enviada exitosamente a {$destino}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al enviar correo: '.$e->getMessage());
        }
    }

    public function emitirNota(Request $request, FacturaElectronica $factura, FacturaService $facturas)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $data = $request->validate([
            'tipo' => 'required|in:debito,credito',
            'monto' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:500',
        ]);

        try {
            $nota = $facturas->emitirNota($factura, $data['tipo'], (float) $data['monto'], $data['motivo']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Nota de {$data['tipo']} {$nota->numero} emitida");
    }

    public function anular(Request $request, FacturaElectronica $factura, FacturaService $facturas)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $data = $request->validate([
            'motivo' => 'nullable|integer|min:1|max:5',
        ]);

        try {
            $facturas->anular($factura, (int) ($data['motivo'] ?? 1));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error comunicando al SIN: '.$e->getMessage());
        }

        Auditoria::create([
            'usuario_id' => auth()->id(),
            'usuario_nombre' => auth()->user()->name ?? 'sistema',
            'accion' => 'anulacion',
            'modelo' => 'FacturaElectronica',
            'modelo_id' => $factura->id,
            'descripcion' => $factura->numero_factura.' (motivo '.($data['motivo'] ?? 1).')',
            'ip' => $request->ip(),
        ]);

        return back()->with('exito', 'Factura anulada ante el SIN.'.$this->enviarCorreo($factura->fresh(), 'anulada'));
    }

    public function revertir(Request $request, FacturaElectronica $factura, FacturaService $facturas)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        try {
            $facturas->revertirAnulacion($factura);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error comunicando al SIN: '.$e->getMessage());
        }

        Auditoria::create([
            'usuario_id' => auth()->id(),
            'usuario_nombre' => auth()->user()->name ?? 'sistema',
            'accion' => 'reversion',
            'modelo' => 'FacturaElectronica',
            'modelo_id' => $factura->id,
            'descripcion' => $factura->numero_factura,
            'ip' => $request->ip(),
        ]);

        return back()->with('exito', 'Anulación revertida: la factura vuelve a EMITIDA');
    }

    public function reporteAnulaciones(Request $request)
    {
        $sucursalId = $this->sucursalPermitidaParaLectura($request);
        // Anti CSV-injection: neutralizar celdas que empiezan con = + - @ (Excel las ejecutaría).
        $esc = function ($v) {
            $v = (string) $v;
            if (preg_match('/^[=+\-@\t\r]/', $v)) {
                $v = "'".$v;
            }

            return '"'.str_replace('"', '""', $v).'"';
        };

        return response()->streamDownload(function () use ($sucursalId, $esc) {
            echo "Numero,CUF,Sucursal,PuntoVenta,Cliente,NIT,Fecha,Total,Recepcion\n";
            FacturaElectronica::with(['venta', 'sucursal', 'puntoVenta'])
                ->where('estado', 'anulada')
                ->when($sucursalId, fn ($q, $s) => $q->where('sucursal_id', $s))
                ->orderBy('id')
                ->lazyById(500)
                ->each(function ($f) use ($esc) {
                    echo implode(',', [
                        $f->numero_factura,
                        $f->cuf,
                        $esc($f->sucursal?->nombre ?? ('Sucursal '.$f->codigo_sucursal)),
                        $esc($f->puntoVenta?->nombre ?? ('POS '.$f->codigo_punto_venta)),
                        $esc($f->venta?->cliente_nombre ?? ''),
                        $esc($f->venta?->nit_cliente ?? ''),
                        $f->fecha_emision?->format('Y-m-d H:i') ?? '',
                        $f->venta?->total ?? 0,
                        $f->codigo_recepcion,
                    ])."\n";
                });
        }, 'anulaciones.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function descargarPdf(FacturaElectronica $factura, FacturaService $facturas)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        if (! $factura->pdf_path || ! Storage::disk('public')->exists($factura->pdf_path)) {
            $path = $facturas->generarPdf($factura);
            $factura->update(['pdf_path' => $path]);
        }

        return Storage::disk('public')->download($factura->pdf_path, $factura->numero_factura.'.pdf');
    }

    public function descargarPdfRollo(FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $factura->load(['venta.detalles', 'sucursal', 'puntoVenta']);
        $pdf = Pdf::loadView('facturas.pdf-rollo', [
            'factura' => $factura,
            'empresa' => Configuracion::empresa(),
        ]);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper([0, 0, 226.77, 800], 'portrait'); // 80mm de ancho, largo dinámico

        return $pdf->download($factura->numero_factura.'-rollo-80mm.pdf');
    }

    public function descargarPdfMedioOficio(FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $factura->load(['venta.detalles', 'sucursal', 'puntoVenta']);
        $pdf = Pdf::loadView('facturas.pdf-medio-oficio', [
            'factura' => $factura,
            'empresa' => Configuracion::empresa(),
        ]);
        $pdf->setOption('isRemoteEnabled', true);
        // Half Letter / Medio Oficio: 5.5 x 8.5 inches = 396 x 612 pt
        $pdf->setPaper([0, 0, 396, 612], 'portrait');

        return $pdf->download($factura->numero_factura.'-medio-oficio.pdf');
    }

    public function descargarPdfRollo58(FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        $factura->load(['venta.detalles', 'sucursal', 'puntoVenta']);
        $pdf = Pdf::loadView('facturas.pdf-rollo-58', [
            'factura' => $factura,
            'empresa' => Configuracion::empresa(),
        ]);
        $pdf->setOption('isRemoteEnabled', true);
        // Rollo 58mm térmico: 58mm = 164.41 pt
        $pdf->setPaper([0, 0, 164.41, 800], 'portrait');

        return $pdf->download($factura->numero_factura.'-rollo-58mm.pdf');
    }

    public function descargarXml(FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        if (! $factura->xml_firmado) {
            return back()->with('error', 'XML no disponible.');
        }

        return response($factura->xml_firmado, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$factura->numero_factura.'.xml"',
        ]);
    }

    public function destroy(FacturaElectronica $factura)
    {
        SucursalContext::autorizaSucursal($factura->sucursal_id);
        if ($factura->estado === 'emitida') {
            return back()->with('error', 'No se puede eliminar una factura EMITIDA (documento fiscal). Anúlala ante el SIN.');
        }

        if ($factura->pdf_path && Storage::disk('public')->exists($factura->pdf_path)) {
            Storage::disk('public')->delete($factura->pdf_path);
        }
        $factura->delete();

        return redirect()->route('facturas.index')->with('exito', 'Registro de factura eliminado');
    }

    private function sucursalPermitidaParaLectura(Request $request): ?int
    {
        $sucursalSolicitada = $request->filled('sucursal_id') ? $request->integer('sucursal_id') : null;

        if ($sucursalSolicitada) {
            SucursalContext::autorizaSucursal($sucursalSolicitada);
        }

        $usuario = $request->user();
        if ($this->usuarioRestringidoASucursal($usuario)) {
            return (int) $usuario->sucursal_id;
        }

        return $sucursalSolicitada;
    }

    private function aplicarSucursalVisible(Builder $query, ?int $sucursalId): Builder
    {
        return $query->when($sucursalId, fn (Builder $subQuery): Builder => $subQuery->where('sucursal_id', $sucursalId));
    }

    private function sucursalesVisibles(Request $request): Collection
    {
        $usuario = $request->user();

        return Sucursal::activas()
            ->when($this->usuarioRestringidoASucursal($usuario), fn (Builder $query): Builder => $query->whereKey($usuario->sucursal_id))
            ->orderBy('codigo')
            ->get();
    }

    private function usuarioRestringidoASucursal(?object $usuario): bool
    {
        return $usuario
            && ! in_array($usuario->rol ?? null, [Rol::OCULTO, 'admin'], true)
            && (bool) $usuario->sucursal_id;
    }
}
