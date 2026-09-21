<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\EventoContingencia;
use App\Models\FacturaElectronica;
use App\Models\NotaFiscal;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class FacturaService
{
    public function __construct(
        protected SiatService $siat,
        protected ContadorService $contadores,
    ) {}

    /**
     * Emite factura electrónica para una venta con_factura y activa.
     * Flujo: XML → CUF → firma → recepción SIN → registro + PDF. Todo transaccional.
     * $opciones: ['contingencia' => bool] usa CAFC con tipoEmision=2 (requiere siat_cafc).
     */
    public function emitirDesdeVenta(Venta $venta, ?int $usuarioId = null, array $opciones = []): FacturaElectronica
    {
        $venta->loadMissing('detalles.producto', 'cliente');

        if ($venta->tipo !== 'con_factura') {
            throw new InvalidArgumentException('Solo se puede facturar una venta CON FACTURA.');
        }
        if ($venta->estado !== 'activa') {
            throw new InvalidArgumentException('Solo se puede facturar una venta ACTIVA.');
        }
        if ($venta->facturaElectronica()->where('estado', '!=', 'rechazada')->exists()) {
            throw new InvalidArgumentException('Esta venta ya tiene una factura electrónica.');
        }
        if ($venta->detalles->isEmpty()) {
            throw new InvalidArgumentException('La venta no tiene items para facturar.');
        }
        $nitCliente = trim((string) ($venta->cliente->nit ?? $venta->nit_cliente ?? ''));
        if ($nitCliente === '') {
            throw new InvalidArgumentException('El cliente necesita NIT/CI para emitir factura. Edítalo en Clientes.');
        }
        if (! NitHelper::formatoValido($nitCliente)) {
            throw new InvalidArgumentException('NIT/CI del cliente inválido ('.NitHelper::mensajeFormato().')');
        }

        $usuarioId ??= Auth::id();
        $contingencia = ! empty($opciones['contingencia']);
        $cafc = $contingencia ? (string) SiatConfig::get('siat_cafc') : null;
        if ($contingencia && $cafc === '') {
            throw new InvalidArgumentException('Contingencia requiere CAFC configurado en Configuración → SIAT.');
        }
        $emision = $contingencia ? 2 : 1;

        // Contexto de sucursal y punto de venta
        $sucursal = $venta->sucursal ?? SucursalContext::sucursal();
        $puntoVenta = $venta->puntoVenta ?? SucursalContext::puntoVenta();
        $codigoSucursal = (int) ($sucursal->codigo ?? 0);
        $codigoPuntoVenta = (int) ($puntoVenta->codigo ?? 0);

        // Numeración por sucursal y punto de venta
        $prefijo = ($codigoSucursal === 0 && $codigoPuntoVenta === 0)
            ? 'FAC-'
            : sprintf('FAC-S%d-P%d-', $codigoSucursal, $codigoPuntoVenta);

        $numero = $this->contadores->siguienteUnico(
            $prefijo,
            fn ($n) => FacturaElectronica::where('numero_factura', $n)->exists()
        );
        $correlativoStr = substr($numero, strlen($prefijo));
        // Mantener como cadena numérica (sin castear a int) para no perder
        // ceros que el CUF exige en sus anchos fijos.
        $numeroFactura = preg_replace('/\D/', '', $correlativoStr) ?: '1';
        $fechaEmision = now();
        $fechaHoraCuf = $fechaEmision->format('YmdHis').substr($fechaEmision->format('u'), 0, 3);

        $cufdActual = $emision === 2 ? null : ($puntoVenta->cufd ?: (string) SiatConfig::get('siat_cufd'));

        // En modo real, un CUFD/CUIS de simulador (SIM-) sería rechazado por
        // el SIN: exigir identidad real antes de gastar numeración.
        if (! SiatConfig::esSimulador() && $emision === 1) {
            $cuisActual = $puntoVenta->cuis ?: (string) SiatConfig::get('siat_cuis');
            if (str_starts_with((string) $cufdActual, 'SIM-') || str_starts_with($cuisActual, 'SIM-')) {
                throw new InvalidArgumentException(
                    'El punto de venta tiene CUIS/CUFD de simulador. En modo REAL solicita primero CUIS y CUFD reales en Sucursales.'
                );
            }
        }

        // Sin CUFD vigente no se emite (igual que exige el SIN en línea).
        if ($emision === 1 && ! $this->tieneCufdVigente($puntoVenta, $cufdActual)) {
            throw new InvalidArgumentException(
                'El punto de venta no tiene CUFD vigente. Solicítalo en Sucursales antes de emitir.'
            );
        }

        $cuf = $this->siat->generarCuf(
            (string) SiatConfig::get('siat_nit', '0'),
            $fechaHoraCuf,
            (string) $codigoSucursal,
            SiatConfig::codigoModalidadSin(),
            $emision,
            1,
            1,
            $numeroFactura,
            (string) $codigoPuntoVenta,
        );

        $xml = $this->construirXml($venta, $numeroFactura, $cuf, $fechaEmision, $nitCliente, $cafc, $sucursal, $puntoVenta, $cufdActual, $puntoVenta->codigo_control);

        try {
            $firmado = $this->siat->firmarXml($xml);
            $resp = $this->siat->recepcionFactura(
                $firmado, $cuf, $numeroFactura, $fechaEmision, $emision, $cafc,
                [
                    'codigoSucursal' => $codigoSucursal,
                    'codigoPuntoVenta' => $codigoPuntoVenta,
                    'cufd' => $cufdActual,
                    'cuis' => $puntoVenta->cuis,
                ]
            );
        } catch (Throwable $e) {
            // Dejar constancia del intento fallido (fuera de la transacción revertida)
            $fallida = FacturaElectronica::create([
                'venta_id' => $venta->id,
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'codigo_sucursal' => $codigoSucursal,
                'codigo_punto_venta' => $codigoPuntoVenta,
                'cuf' => $cuf,
                'cufd' => $cufdActual,
                'numero_factura' => $numero,
                'estado' => 'rechazada',
                'fecha_emision' => $fechaEmision,
                'xml_firmado' => $xml,
                'observaciones_sin' => mb_substr($e->getMessage(), 0, 2000),
                'simulada' => SiatConfig::esSimulador(),
                'usuario_id' => $usuarioId,
                'tipo_emision' => $emision,
                'cafc' => $cafc,
            ]);
            throw new InvalidArgumentException('SIN rechazó/error: '.$e->getMessage().' (registro #'.$fallida->id.' guardado como RECHAZADA).');
        }

        return DB::transaction(function () use ($venta, $usuarioId, $numero, $cuf, $fechaEmision, $firmado, $resp, $nitCliente, $emision, $cafc, $sucursal, $puntoVenta, $cufdActual) {
            $ok = (bool) ($resp['transaccion'] ?? false);

            // Si es contingencia y hay un evento abierto para esta sucursal, se vincula automáticamente
            $eventoId = null;
            if ($emision === 2) {
                $eventoId = EventoContingencia::where('estado', 'abierto')
                    ->where(function ($q) use ($sucursal) {
                        $q->where('sucursal_id', $sucursal->id)->orWhereNull('sucursal_id');
                    })
                    ->latest('id')
                    ->value('id');
            }

            $codSucursal = (int) ($sucursal->codigo ?? 0);
            $codPuntoVenta = (int) ($puntoVenta->codigo ?? 0);

            // Actualizar la venta con la sucursal y punto de venta si aún no los tiene
            $venta->update([
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'codigo_sucursal' => $codSucursal,
                'codigo_punto_venta' => $codPuntoVenta,
            ]);

            $factura = FacturaElectronica::create([
                'venta_id' => $venta->id,
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'codigo_sucursal' => $codSucursal,
                'codigo_punto_venta' => $codPuntoVenta,
                'cuf' => $cuf,
                'cufd' => $cufdActual,
                'numero_factura' => $numero,
                'estado' => $ok ? 'emitida' : 'observada',
                'fecha_emision' => $fechaEmision,
                'xml_firmado' => $firmado,
                'xml_respuesta' => $resp['raw'] ?? json_encode($resp, JSON_UNESCAPED_UNICODE),
                'codigo_recepcion' => $resp['codigoRecepcion'] ?? null,
                'transaccion' => $ok,
                'leyenda' => $this->leyenda(),
                'observaciones_sin' => $resp['codigoDescripcion'] ?? null,
                'simulada' => ! empty($resp['simulado']),
                'usuario_id' => $usuarioId,
                'tipo_emision' => $emision,
                'cafc' => $cafc,
                'evento_id' => $eventoId,
                'en_paquete' => false,
            ]);

            $venta->update([
                'numero_factura_siat' => $numero,
                'codigo_autorizacion' => $cuf,
                'nit_cliente' => $nitCliente,
            ]);

            $factura->update(['pdf_path' => $this->generarPdf($factura->fresh('venta'))]);

            return $factura->fresh();
        });
    }

    /**
     * Emite nota de débito o crédito contra una factura emitida.
     */
    public function emitirNota(FacturaElectronica $factura, string $tipo, float $monto, string $motivo, ?int $usuarioId = null): NotaFiscal
    {
        if (! in_array($tipo, ['debito', 'credito'], true)) {
            throw new InvalidArgumentException('Tipo de nota no válido.');
        }
        if ($factura->estado !== 'emitida') {
            throw new InvalidArgumentException('Solo se emiten notas contra facturas EMITIDAS.');
        }
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        $usuarioId ??= Auth::id();

        try {
            $resp = $this->siat->recepcionNota((string) $factura->cuf, $tipo, $monto, $motivo);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('SIN error: '.$e->getMessage());
        }

        return DB::transaction(function () use ($factura, $tipo, $monto, $motivo, $usuarioId, $resp) {
            return NotaFiscal::create([
                'factura_id' => $factura->id,
                'tipo' => $tipo,
                'numero' => $this->contadores->siguiente($tipo === 'debito' ? 'ND-' : 'NC-'),
                'monto' => round($monto, 2),
                'motivo' => $motivo,
                'estado' => 'emitida',
                'cuf' => $resp['cuf'] ?? null,
                'codigo_recepcion' => $resp['codigoRecepcion'] ?? null,
                'simulada' => SiatConfig::esSimulador(),
                'usuario_id' => $usuarioId,
            ]);
        });
    }

    public function anular(FacturaElectronica $factura, int $codigoMotivo = 1): FacturaElectronica
    {
        if ($factura->estado !== 'emitida') {
            throw new InvalidArgumentException('Solo se puede anular una factura EMITIDA.');
        }

        $factura->loadMissing('puntoVenta');
        $resp = $this->siat->anulacionFactura((string) $factura->cuf, $codigoMotivo, [
            'codigoSucursal' => $factura->codigo_sucursal,
            'codigoPuntoVenta' => $factura->codigo_punto_venta,
            'cufd' => $factura->cufd,
            'cuis' => $factura->puntoVenta?->cuis,
        ]);
        if (! ($resp['transaccion'] ?? false)) {
            throw new InvalidArgumentException('El SIN no aceptó la anulación: '.($resp['codigoDescripcion'] ?? 'sin detalle'));
        }

        $factura->update(['estado' => 'anulada']);

        return $factura->fresh();
    }

    public function revertirAnulacion(FacturaElectronica $factura): FacturaElectronica
    {
        if ($factura->estado !== 'anulada') {
            throw new InvalidArgumentException('Solo se puede revertir una factura ANULADA.');
        }

        // Validación de plazo legal SIN: hasta las 23:59:59 del día 9 del mes siguiente a la emisión
        if ($factura->fecha_emision) {
            $fechaEmision = $factura->fecha_emision instanceof CarbonInterface
                ? $factura->fecha_emision
                : Carbon::parse($factura->fecha_emision);

            $limiteLegal = $fechaEmision->copy()->addMonthNoOverflow()->startOfMonth()->addDays(8)->endOfDay();
            if (now()->gt($limiteLegal)) {
                throw new InvalidArgumentException(
                    "El plazo legal según normativa SIN para revertir la anulación venció el {$limiteLegal->format('d/m/Y H:i')}."
                );
            }
        }

        $resp = $this->siat->reversionAnulacion((string) $factura->cuf);
        if (! ($resp['transaccion'] ?? false)) {
            throw new InvalidArgumentException('El SIN no aceptó la reversión: '.($resp['codigoDescripcion'] ?? 'sin detalle'));
        }

        $factura->update(['estado' => 'emitida']);

        return $factura->fresh();
    }

    public function leyenda(): string
    {
        return (string) SiatConfig::get(
            'siat_leyenda',
            'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos.'
        );
    }

    /**
     * ¿Hay CUFD vigente para emitir en este punto de venta?
     * Revisa el PV y, como respaldo, el CUFD global de Casa Matriz.
     */
    protected function tieneCufdVigente(?PuntoVenta $puntoVenta, ?string $cufdActual): bool
    {
        if ($cufdActual === null || $cufdActual === '') {
            return false;
        }
        if ($puntoVenta?->cufd && $puntoVenta->cufd === $cufdActual) {
            return $puntoVenta->tieneCufdVigente();
        }
        try {
            $vigencia = (string) SiatConfig::get('siat_cufd_vigencia', '');

            return $vigencia !== '' && Carbon::parse($vigencia)->isFuture();
        } catch (Throwable) {
            return false;
        }
    }

    protected function construirXml(
        Venta $venta,
        string $numeroFactura,
        string $cuf,
        \DateTimeInterface $fechaEmision,
        string $nitCliente,
        ?string $cafc = null,
        ?Sucursal $sucursal = null,
        ?PuntoVenta $puntoVenta = null,
        ?string $cufd = null,
        ?string $codigoControl = null,
    ): string {
        $e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_XML1, 'UTF-8');
        $nitEmisor = $e(SiatConfig::get('siat_nit', '0'));
        $razonEmisor = $e(SiatConfig::get('siat_razon_social', SiatConfig::get('empresa_nombre', 'GISECA SRL')));
        $codigoSucursal = $sucursal ? (string) $sucursal->codigo : (string) SiatConfig::get('siat_sucursal', '0');
        $codigoPuntoVenta = $puntoVenta ? (string) $puntoVenta->codigo : (string) SiatConfig::get('siat_punto_venta', '0');
        $direccion = $e($sucursal?->direccion ?: SiatConfig::get('siat_direccion', SiatConfig::get('empresa_direccion', '')));
        $telefono = $e($sucursal?->telefono ?: SiatConfig::get('siat_telefono', SiatConfig::get('empresa_telefono', '')));
        $municipio = $e($sucursal?->municipio ?: SiatConfig::get('siat_ciudad', 'Santa Cruz'));
        $cufdValor = $e($cufd ?: SiatConfig::get('siat_cufd', ''));
        $fecha = $fechaEmision->format('Y-m-d\TH:i:s.v');
        $clienteNombre = $e($venta->cliente_nombre);

        // Mapeo fiscal de método de pago según SIN
        $metodoPago = 1;
        if ($venta->modalidad === 'credito') {
            $metodoPago = 6;
        } else {
            $obs = mb_strtolower((string) ($venta->observaciones ?? ''));
            if (str_contains($obs, 'tarjeta')) {
                $metodoPago = 2;
            } elseif (str_contains($obs, 'cheque')) {
                $metodoPago = 3;
            } elseif (str_contains($obs, 'qr') || str_contains($obs, 'transferencia')) {
                $metodoPago = 5;
            }
        }

        // Tipo de documento: 5 para NIT (>= 10 dígitos), 1 para CI
        $digitos = NitHelper::soloDigitos($nitCliente);
        $tipoDoc = strlen($digitos) >= 10 ? 5 : 1;

        $detalle = '';
        foreach ($venta->detalles as $it) {
            // Homologación SIN por producto (Fase A); por defecto 99100/58
            $codigoSin = $it->producto->codigo_sin ?? '99100';
            $unidadSin = $it->producto->unidad_sin ?? '58';
            $detalle .= '<detalle>'
                .'<actividadEconomica>474100</actividadEconomica>'
                .'<codigoProductoSin>'.$e($codigoSin).'</codigoProductoSin>'
                .'<codigoProducto>'.$e($it->codigo_producto).'</codigoProducto>'
                .'<descripcion>'.$e($it->descripcion_producto).'</descripcion>'
                .'<cantidad>'.number_format((float) $it->cantidad, 2, '.', '').'</cantidad>'
                .'<unidadMedida>'.$e($unidadSin).'</unidadMedida>'
                .'<precioUnitario>'.number_format((float) $it->precio_unitario, 2, '.', '').'</precioUnitario>'
                .'<montoDescuento>0.00</montoDescuento>'
                .'<subTotal>'.number_format((float) $it->subtotal, 2, '.', '').'</subTotal>'
                .'</detalle>';
        }

        $raizXml = SiatConfig::etiquetaRaizXml();

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<'.$raizXml.'>'
            .'<cabecera>'
            ."<nitEmisor>{$nitEmisor}</nitEmisor>"
            ."<razonSocialEmisor>{$razonEmisor}</razonSocialEmisor>"
            ."<municipio>{$municipio}</municipio>"
            ."<telefono>{$telefono}</telefono>"
            ."<numeroFactura>{$e($numeroFactura)}</numeroFactura>"
            ."<cuf>{$e($cuf)}</cuf>"
            ."<cufd>{$cufdValor}</cufd>"
            .($cafc ? "<cafc>{$e($cafc)}</cafc>" : '')
            // Computarizada exige el código de control del CUFD en el XML.
            .($codigoControl && SiatConfig::esComputarizada() ? "<codigoControl>{$e($codigoControl)}</codigoControl>" : '')
            ."<codigoSucursal>{$codigoSucursal}</codigoSucursal>"
            ."<direccion>{$direccion}</direccion>"
            ."<codigoPuntoVenta>{$codigoPuntoVenta}</codigoPuntoVenta>"
            ."<fechaEmision>{$fecha}</fechaEmision>"
            ."<nombreRazonSocial>{$clienteNombre}</nombreRazonSocial>"
            ."<codigoTipoDocumentoIdentidad>{$tipoDoc}</codigoTipoDocumentoIdentidad>"
            ."<numeroDocumento>{$e($nitCliente)}</numeroDocumento>"
            ."<codigoCliente>{$e($venta->cliente_id ?? $nitCliente)}</codigoCliente>"
            ."<codigoMetodoPago>{$metodoPago}</codigoMetodoPago>"
            .'<montoTotal>'.number_format((float) $venta->total, 2, '.', '').'</montoTotal>'
            .'<montoTotalSujetoIva>'.number_format((float) $venta->total, 2, '.', '').'</montoTotalSujetoIva>'
            .'<codigoMoneda>1</codigoMoneda><tipoCambio>1</tipoCambio>'
            .'<montoTotalMoneda>'.number_format((float) $venta->total, 2, '.', '').'</montoTotalMoneda>'
            .'<descuentoAdicional>'.number_format((float) $venta->descuento, 2, '.', '').'</descuentoAdicional>'
            .'<codigoExcepcion>0</codigoExcepcion>'
            .'<leyenda>'.$e($this->leyenda()).'</leyenda>'
            .'<usuario>'.$e(auth()->user()->name ?? 'admin').'</usuario>'
            .'<codigoDocumentoSector>1</codigoDocumentoSector>'
            .'</cabecera>'
            .$detalle
            .'</'.$raizXml.'>';
    }

    public function generarPdf(FacturaElectronica $factura): string
    {
        $factura->loadMissing('venta');
        $pdf = Pdf::loadView('facturas.pdf', [
            'factura' => $factura,
            'empresa' => Configuracion::empresa(),
        ]);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('letter');

        $path = 'facturas/'.$factura->numero_factura.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    public function urlQr(FacturaElectronica $factura): string
    {
        $nit = SiatConfig::get('siat_nit', '0');
        $data = "https://siat.impuestos.gob.bo/consulta/QR?nit={$nit}&cuf={$factura->cuf}&numero={$factura->numero_factura}&t=2";

        return 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode($data);
    }
}
