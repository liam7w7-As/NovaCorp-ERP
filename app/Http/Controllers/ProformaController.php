<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\Proforma;
use App\Services\ProformaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProformaController extends Controller
{
    public function index(Request $request)
    {
        $query = Proforma::with('cliente')->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }
        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('numero', 'like', "%{$q}%")
                    ->orWhere('cliente_nombre', 'like', "%{$q}%");
            });
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->get('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->get('hasta'));
        }

        $proformas = $query->paginate(20)->withQueryString();

        $resumen = [];
        foreach (Proforma::ESTADOS as $e) {
            $resumen[$e] = Proforma::where('estado', $e)->count();
        }

        return view('proformas.index', [
            'proformas' => $proformas,
            'resumen' => $resumen,
            'estado' => $request->get('estado', ''),
            'q' => $request->get('q', ''),
            'desde' => $request->get('desde', ''),
            'hasta' => $request->get('hasta', ''),
        ]);
    }

    public function create()
    {
        return view('proformas.create', [
            'clientes' => Cliente::orderBy('nombre')->get(),
            'fechaHoy' => date('Y-m-d'),
            'fechaValidez' => date('Y-m-d', strtotime('+15 days')),
        ]);
    }

    protected function reglasItems(): array
    {
        return [
            'cliente_id' => 'nullable|exists:clientes,id',
            'cliente_nuevo' => 'nullable|string|max:255',
            'fecha' => 'required|date',
            'validez' => 'nullable|date|after_or_equal:fecha',
            'contacto' => 'nullable|string|max:150',
            'telefono' => 'nullable|string|max:50',
            'tiempo_entrega' => 'nullable|string|max:150',
            'condiciones_pago' => 'nullable|string|max:150',
            'garantia' => 'nullable|string|max:150',
            'nota' => 'nullable|string',
            'reserva_stock' => 'nullable|boolean',
            'descuento' => 'nullable|numeric|min:0',
            'descuento_tipo' => 'nullable|in:fijo,porcentaje',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio' => 'required|numeric|min:0',
        ];
    }

    protected function resolverCliente(Request $request): Cliente
    {
        $nuevo = trim($request->input('cliente_nuevo', ''));
        if ($nuevo !== '') {
            $existente = Cliente::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nuevo)])->first();

            return $existente ?? Cliente::create(['nombre' => $nuevo]);
        }

        $cliente = Cliente::find($request->input('cliente_id'));
        abort_if(! $cliente, 422, 'Selecciona o crea un cliente.');

        return $cliente;
    }

    public function store(Request $request, ProformaService $proformas)
    {
        $data = $request->validate($this->reglasItems());
        $cliente = $this->resolverCliente($request);

        $proforma = DB::transaction(function () use ($data, $request, $cliente, $proformas) {
            $items = [];
            foreach ($data['items'] as $it) {
                $producto = Producto::findOrFail($it['producto_id']);
                $items[] = [
                    'producto' => $producto,
                    'cantidad' => round((float) $it['cantidad'], 2),
                    'precio' => round((float) $it['precio'], 2),
                ];
            }
            $totales = $proformas->calcularTotales(
                array_map(fn ($i) => ['cantidad' => $i['cantidad'], 'precio' => $i['precio']], $items),
                (float) ($data['descuento'] ?? 0),
                $data['descuento_tipo'] ?? null
            );

            $p = Proforma::create([
                'numero' => $proformas->siguienteNumero(),
                'fecha' => $data['fecha'],
                'validez' => $data['validez'] ?? null,
                'cliente_id' => $cliente->id,
                'cliente_nombre' => $cliente->nombre,
                'estado' => 'borrador',
                'subtotal' => $totales['subtotal'],
                'descuento' => $totales['descuento'],
                'descuento_tipo' => $totales['descuento_tipo'],
                'total' => $totales['total'],
                'nota' => $request->input('nota'),
                'reserva_stock' => (bool) $request->input('reserva_stock', false),
                'usuario_id' => Auth::id(),
                'contacto' => $request->input('contacto'),
                'telefono' => $request->input('telefono'),
                'tiempo_entrega' => $request->input('tiempo_entrega'),
                'condiciones_pago' => $request->input('condiciones_pago'),
                'garantia' => $request->input('garantia'),
            ]);

            foreach ($items as $it) {
                $p->detalles()->create([
                    'producto_id' => $it['producto']->id,
                    'codigo_interno' => $it['producto']->codigo_interno,
                    'codigo_producto' => $it['producto']->codigo,
                    'descripcion_producto' => $it['producto']->descripcion,
                    'cantidad' => $it['cantidad'],
                    'precio_unitario' => $it['precio'],
                    'subtotal' => round($it['cantidad'] * $it['precio'], 2),
                ]);
            }

            return $p;
        });

        return redirect()->route('proformas.show', $proforma)
            ->with('exito', "Proforma {$proforma->numero} creada");
    }

    public function show(Proforma $proforma)
    {
        $proforma->load('detalles.producto', 'cliente', 'venta.facturaElectronica');

        return view('proformas.show', [
            'proforma' => $proforma,
            'empresa' => Configuracion::empresa(),
            'membretado' => Configuracion::membretado(),
        ]);
    }

    /**
     * PDF oficial para abrir en pestaña (inline, sin descargar).
     */
    public function pdf(Proforma $proforma)
    {
        $proforma->load('detalles.producto', 'cliente');

        $logo = Configuracion::logo();
        $logoPath = public_path($logo['path'] ?? 'images/logo.png');
        if (! is_file($logoPath) && ! empty($logo['path']) && ! str_contains($logo['path'], '..')) {
            $candidato = storage_path('app/public/'.$logo['path']);
            if (is_file($candidato)) {
                $logoPath = $candidato;
            }
        }

        $pdf = Pdf::loadView('proformas.pdf', [
            'proforma' => $proforma,
            'empresa' => Configuracion::empresa(),
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ]);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('letter');

        return $pdf->stream($proforma->numero.'.pdf');
    }

    public function edit(Proforma $proforma)
    {
        if ($proforma->esta_convertida) {
            return redirect()->route('proformas.show', $proforma)
                ->with('error', 'No se puede editar una proforma convertida.');
        }

        $proforma->load('detalles');

        return view('proformas.edit', [
            'proforma' => $proforma,
            'clientes' => Cliente::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Proforma $proforma, ProformaService $proformas)
    {
        if ($proforma->esta_convertida) {
            return back()->with('error', 'No se puede editar una proforma convertida.');
        }

        $data = $request->validate($this->reglasItems());
        $cliente = $this->resolverCliente($request);

        DB::transaction(function () use ($data, $request, $proforma, $cliente, $proformas) {
            $items = [];
            foreach ($data['items'] as $it) {
                $producto = Producto::findOrFail($it['producto_id']);
                $items[] = [
                    'producto' => $producto,
                    'cantidad' => round((float) $it['cantidad'], 2),
                    'precio' => round((float) $it['precio'], 2),
                ];
            }
            $totales = $proformas->calcularTotales(
                array_map(fn ($i) => ['cantidad' => $i['cantidad'], 'precio' => $i['precio']], $items),
                (float) ($data['descuento'] ?? 0),
                $data['descuento_tipo'] ?? null
            );

            $proforma->update([
                'fecha' => $data['fecha'],
                'validez' => $data['validez'] ?? null,
                'cliente_id' => $cliente->id,
                'cliente_nombre' => $cliente->nombre,
                'subtotal' => $totales['subtotal'],
                'descuento' => $totales['descuento'],
                'descuento_tipo' => $totales['descuento_tipo'],
                'total' => $totales['total'],
                'nota' => $request->input('nota'),
                'reserva_stock' => (bool) $request->input('reserva_stock', false),
                'contacto' => $request->input('contacto'),
                'telefono' => $request->input('telefono'),
                'tiempo_entrega' => $request->input('tiempo_entrega'),
                'condiciones_pago' => $request->input('condiciones_pago'),
                'garantia' => $request->input('garantia'),
            ]);

            $proforma->detalles()->delete();
            foreach ($items as $it) {
                $proforma->detalles()->create([
                    'producto_id' => $it['producto']->id,
                    'codigo_interno' => $it['producto']->codigo_interno,
                    'codigo_producto' => $it['producto']->codigo,
                    'descripcion_producto' => $it['producto']->descripcion,
                    'cantidad' => $it['cantidad'],
                    'precio_unitario' => $it['precio'],
                    'subtotal' => round($it['cantidad'] * $it['precio'], 2),
                ]);
            }
        });

        return redirect()->route('proformas.show', $proforma->fresh())
            ->with('exito', 'Proforma actualizada');
    }

    public function destroy(Proforma $proforma)
    {
        if ($proforma->esta_convertida) {
            return back()->with('error', 'No se puede eliminar una proforma convertida.');
        }

        $proforma->delete();

        return redirect()->route('proformas.index')->with('exito', 'Proforma eliminada');
    }

    public function cambiarEstado(Request $request, Proforma $proforma, ProformaService $proformas)
    {
        $data = $request->validate([
            'estado' => 'required|in:borrador,enviada,aprobada,rechazada,vencida',
        ]);

        try {
            $proformas->cambiarEstado($proforma, $data['estado']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Estado actualizado a '.strtoupper($data['estado']));
    }

    public function convertirAVenta(Request $request, Proforma $proforma, ProformaService $proformas)
    {
        $data = $request->validate([
            'tipo' => 'required|in:con_factura,sin_factura',
            'modalidad' => 'required|in:contado,credito',
            'metodo' => 'nullable|string|max:100',
        ]);

        try {
            $venta = $proformas->convertirAVenta(
                $proforma,
                $data['tipo'],
                $data['modalidad'],
                $data['metodo'] ?? 'Efectivo'
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('ventas.index', ['q' => $venta->numero])
            ->with('exito', "Proforma convertida a venta {$venta->numero}. Comprobante {$venta->comprobante_numero} generado.");
    }

    /**
     * GET /proformas/buscar-producto?q=... (reutiliza el índice de productos)
     */
    public function buscarProducto(Request $request)
    {
        $q = trim($request->get('q', ''));

        $lista = Producto::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', "%{$q}%")
                        ->orWhere('codigo_interno', 'like', "%{$q}%")
                        ->orWhere('descripcion', 'like', "%{$q}%")
                        ->orWhere('equivalente', 'like', "%{$q}%")
                        ->orWhere('marca', 'like', "%{$q}%");
                });
            })
            ->orderBy('descripcion')
            ->limit(15)
            ->get(['id', 'codigo', 'codigo_interno', 'equivalente', 'descripcion', 'marca', 'unidad', 'precio', 'stock', 'stock_reservado']);

        return response()->json($lista);
    }
}
