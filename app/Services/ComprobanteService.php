<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Venta;
use Illuminate\Support\Facades\Auth;

class ComprobanteService
{
    public function __construct(protected ContadorService $contadores) {}

    protected function describirItems(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $partes = array_map(function ($it) {

            $codigoInterno = $it['codigo_interno'] ?? null;
            $descripcion = $it['descripcion_producto']
                ?? $it['codigo_producto']
                ?? '?';

            $texto = $codigoInterno
                ? "{$codigoInterno} - {$descripcion}"
                : $descripcion;

            return $texto.' (x'.($it['cantidad'] ?? 0).')';
        }, $items);

        if (count($partes) <= 3) {
            return implode(', ', $partes);
        }

        return implode(', ', array_slice($partes, 0, 3))
            .' y '.(count($partes) - 3).' producto(s) más';
    }

    protected function siguienteComprobante(string $prefijo): string
    {
        return $this->contadores->siguienteUnico(
            $prefijo,
            fn ($n) => Comprobante::withTrashed()->where('numero', $n)->exists()
        );
    }

    public function crearParaCompra(Compra $compra, array $items = [], string $metodo = 'Efectivo'): Comprobante
    {
        $numero = $this->siguienteComprobante('EGR-');
        $esCredito = ($compra->modalidad ?? 'contado') === 'credito';

        $comp = Comprobante::create([
            'numero' => $numero,
            'tipo' => 'egreso',
            'concepto' => $this->describirItems($items) ?: "Compra {$compra->numero}",
            'entidad' => $compra->proveedor_nombre,
            'monto' => $compra->total,
            'fecha' => $compra->fecha,
            'hora' => now()->format('H:i'),
            'metodo' => $esCredito ? 'Crédito (pendiente de pago)' : $metodo,
            'referencia' => $compra->numero,
            'usuario_id' => Auth::id(),
            'origen_compra_id' => $compra->id,
        ]);

        $comp->pagos()->create([
            'forma_pago' => $esCredito ? 'otro' : $this->mapearMetodo($metodo),
            'monto' => $compra->total,
        ]);

        $compra->update([
            'comprobante_numero' => $numero,
            // Contado nace pagado; crédito nace con saldo pendiente
            'pagado' => $esCredito ? 0 : $compra->total,
        ]);

        return $comp;
    }

    public function crearParaVenta(Venta $venta, string $metodo = 'Efectivo'): Comprobante
    {
        $numero = $this->siguienteComprobante('ING-');
        $esCredito = $venta->modalidad === 'credito';

        $comp = Comprobante::create([
            'numero' => $numero,
            'tipo' => 'ingreso',
            'concepto' => "Venta {$venta->numero} — {$venta->cliente_nombre}".($esCredito ? ' (crédito)' : ''),
            'entidad' => $venta->cliente_nombre,
            'monto' => $venta->total,
            'fecha' => $venta->fecha,
            'hora' => now()->format('H:i'),
            'metodo' => $esCredito ? 'Crédito (pendiente de cobro)' : $metodo,
            'referencia' => $venta->numero,
            'usuario_id' => Auth::id(),
            'origen_venta_id' => $venta->id,
        ]);

        $comp->pagos()->create([
            'forma_pago' => $esCredito ? 'otro' : $this->mapearMetodo($metodo),
            'monto' => $venta->total,
        ]);

        $venta->update([
            'comprobante_numero' => $numero,
            // Contado nace cobrado; crédito nace con saldo pendiente
            'pagado' => $esCredito ? 0 : $venta->total,
        ]);

        return $comp;
    }

    public function crearManual(array $data): Comprobante
    {
        $prefijo = ($data['tipo'] ?? 'ingreso') === 'egreso' ? 'EGR-' : 'ING-';
        $numero = $this->siguienteComprobante($prefijo);

        $comp = Comprobante::create([
            'numero' => $numero,
            'tipo' => $data['tipo'] ?? 'ingreso',
            'concepto' => $data['concepto'],
            'entidad' => $data['entidad'] ?? null,
            'monto' => $data['monto'],
            'nota' => $data['nota'] ?? null,
            'fecha' => $data['fecha'] ?? date('Y-m-d'),
            'hora' => now()->format('H:i'),
            'metodo' => $data['metodo'] ?? 'Efectivo',
            'referencia' => $data['referencia'] ?? null,
            'usuario_id' => Auth::id(),
        ]);

        $comp->pagos()->create([
            'forma_pago' => $this->mapearMetodo($data['metodo'] ?? 'Efectivo'),
            'banco' => $data['banco'] ?? null,
            'cuenta' => $data['cuenta'] ?? null,
            'referencia' => $data['numero_referencia'] ?? null,
            'monto' => $data['monto'],
        ]);

        return $comp;
    }

    public function mapearMetodo(string $metodo): string
    {
        return match (mb_strtolower(trim($metodo))) {
            'efectivo' => 'efectivo',
            'transferencia' => 'transferencia',
            'qr' => 'qr',
            'tarjeta' => 'tarjeta',
            'cheque' => 'cheque',
            default => str_contains(mb_strtolower($metodo), 'credito') ? 'otro' : 'otro',
        };
    }
}
