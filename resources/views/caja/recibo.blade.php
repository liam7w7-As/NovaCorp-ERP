@extends('layouts.app')

@section('title', 'Recibo de Cobro')

@section('content')
    @php
        $venta = $cobro->venta;
        $cuota = $cobro->cuota;
        $comprobante = $cobro->comprobante;
    @endphp

    @if (session('exito'))
        <div class="recibo-alerta no-imprimir">{{ session('exito') }}</div>
    @endif

    <div class="recibo-wrap">
        <section class="recibo-documento">
            <div class="recibo-banner">
                <div class="recibo-empresa">GISECA SRL</div>
                <div class="recibo-titulo">RECIBO DE COBRO</div>
            </div>

            <div class="recibo-cuerpo">
                <div class="recibo-grid">
                    <div>
                        <span>Recibo</span>
                        <strong>{{ $comprobante?->numero ?? 'COBRO-'.$cobro->id }}</strong>
                    </div>
                    <div>
                        <span>Fecha</span>
                        <strong>{{ $cobro->fecha->format('Y-m-d') }}</strong>
                    </div>
                    <div>
                        <span>Venta</span>
                        <strong>{{ $venta?->numero ?? '—' }}</strong>
                    </div>
                    <div>
                        <span>Cliente</span>
                        <strong>{{ $venta?->cliente_nombre ?? '—' }}</strong>
                    </div>
                </div>

                <div class="recibo-monto">
                    <span>Monto cobrado</span>
                    <strong>Bs {{ formatoMoneda($cobro->monto) }}</strong>
                    <small>{{ montoALetras($cobro->monto) }}</small>
                </div>

                <table class="recibo-tabla">
                    <tbody>
                        <tr>
                            <th>Forma de pago</th>
                            <td>{{ $cobro->metodo }}</td>
                        </tr>
                        <tr>
                            <th>Cuota aplicada</th>
                            <td>
                                @if ($cuota)
                                    #{{ $cuota->numero }} · Vence {{ $cuota->fecha_vencimiento->format('Y-m-d') }}
                                @else
                                    Saldo general
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Saldo restante venta</th>
                            <td>Bs {{ formatoMoneda($venta?->fresh()->saldo ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>Referencia</th>
                            <td>{{ $cobro->referencia ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th>Observaciones</th>
                            <td>{{ $cobro->observaciones ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th>Registrado por</th>
                            <td>{{ $cobro->usuario?->name ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="recibo-firmas">
                    <div><span>Recibí conforme</span></div>
                    <div><span>Elaborado por</span></div>
                </div>
            </div>
        </section>

        <aside class="recibo-acciones no-imprimir">
            <button class="btn-giseca btn-primario" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            <a class="btn-giseca btn-outline" href="{{ route('cuentas.index') }}"><i class="bi bi-arrow-left"></i> Cuentas</a>
            @if ($comprobante)
                <a class="btn-giseca btn-outline" href="{{ route('comprobantes.show', $comprobante) }}"><i class="bi bi-receipt"></i> Comprobante</a>
            @endif
        </aside>
    </div>
@endsection

@push('styles')
    <style>
        .recibo-alerta{max-width:560px;margin:0 auto 14px;background:var(--gc-verde-suave);color:var(--gc-verde);padding:10px 14px;border-radius:6px;font-size:13px}.recibo-wrap{display:grid;grid-template-columns:minmax(0,560px) 180px;gap:16px;justify-content:center;align-items:start}.recibo-documento{background:#fff;border:1px solid var(--gc-borde);border-radius:6px;overflow:hidden;color:#222}.recibo-banner{background:#5f6368;color:#fff;text-align:center;padding:16px}.recibo-empresa{font-weight:800;font-size:15px}.recibo-titulo{font-size:12px;font-weight:700;margin-top:3px}.recibo-cuerpo{padding:18px}.recibo-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;border-bottom:1px solid #e5e7eb;padding-bottom:12px}.recibo-grid span,.recibo-monto span,.recibo-tabla th{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:#6b7280}.recibo-grid strong{display:block;font-size:13px;margin-top:3px}.recibo-monto{background:#f8fafc;border:1px solid #e5e7eb;border-radius:5px;padding:12px 14px;margin:14px 0}.recibo-monto strong{display:block;font-size:22px;margin-top:2px;color:#138447}.recibo-monto small{display:block;color:#6b7280;font-size:11px;margin-top:3px}.recibo-tabla{width:100%;border-collapse:collapse;font-size:12.5px}.recibo-tabla th,.recibo-tabla td{border-bottom:1px solid #e5e7eb;padding:8px 6px;text-align:left;vertical-align:top}.recibo-tabla th{width:36%}.recibo-firmas{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:48px;text-align:center;font-size:11px}.recibo-firmas div{border-top:1px solid #222;padding-top:6px}.recibo-acciones{display:grid;gap:8px}.recibo-acciones .btn-giseca{justify-content:center}@media(max-width:820px){.recibo-wrap{grid-template-columns:1fr}.recibo-acciones{grid-template-columns:1fr 1fr}.recibo-acciones .btn-giseca:first-child{grid-column:1/-1}}@media(max-width:560px){.recibo-grid{grid-template-columns:1fr}.recibo-acciones{grid-template-columns:1fr}}@media print{.gc-sidebar,.gc-topbar,.no-imprimir{display:none!important}.gc-shell,.gc-main,.gc-content{display:block!important;margin:0!important;padding:0!important}.recibo-wrap{display:block}.recibo-documento{border:0;border-radius:0;max-width:100%}}
    </style>
@endpush
