{{-- Documento formal de comprobante. Variable: $comprobante (con pagos cargados) --}}
@php
  $pagos = $comprobante->pagos && $comprobante->pagos->count() ? $comprobante->pagos : collect([(object)['forma_pago' => $comprobante->metodo, 'banco' => null, 'cuenta' => null, 'referencia' => null, 'nota' => null, 'monto' => $comprobante->monto]]);
  $asignado = $comprobante->pagos->sum(fn($p) => (float) $p->monto);
  $saldo = round((float) $comprobante->monto - $asignado, 2);
  $esMultiple = $comprobante->pagos->count() > 1;
  $principal = $comprobante->pagos->first();
  $etEntidad = $comprobante->tipo === 'egreso' ? 'Beneficiario(s)' : 'Recibido de';
  $etBanco = $comprobante->tipo === 'egreso' ? 'Banco Destino' : 'Banco Origen';
  $etCuenta = $comprobante->tipo === 'egreso' ? 'Cta. de Origen' : 'Cta. Destino';
@endphp
<div class="comprobante-formal">
  <div class="banner">
    <div class="empresa">GISECA SRL</div>
    <div class="tipo">COMPROBANTE DE {{ strtoupper($comprobante->tipo) }}</div>
  </div>
  <div class="cuerpo">
    <div class="fila-campos">
      <div class="campo"><div class="etiqueta">Nro. Comprobante</div><div class="valor fw-bold">{{ $comprobante->numero }}</div></div>
      <div class="campo"><div class="etiqueta">Fecha de Pago</div><div class="valor">{{ $comprobante->fecha->format('Y-m-d') }}</div></div>
    </div>
    <div class="fila-campos">
      <div class="campo"><div class="etiqueta">{{ $etEntidad }}</div><div class="valor">{{ $comprobante->entidad ?: '—' }}</div></div>
      <div class="campo"><div class="etiqueta">Hora</div><div class="valor">{{ $comprobante->hora ?: '—' }}</div></div>
    </div>
    <div class="fila-campos">
      <div class="campo"><div class="etiqueta">Forma de Pago</div><div class="valor">{{ $esMultiple ? 'Múltiple (ver detalle)' : ($principal->forma_pago ?? $comprobante->metodo) }}</div></div>
      <div class="campo"><div class="etiqueta">{{ $etBanco }}</div><div class="valor">{{ $esMultiple ? '—' : ($principal->banco ?? '—') }}</div></div>
    </div>
    <div class="fila-campos" style="border-bottom:none;">
      <div class="campo"><div class="etiqueta">{{ $etCuenta }}</div><div class="valor">{{ $esMultiple ? '—' : ($principal->cuenta ?? '—') }}</div></div>
      <div class="campo"><div class="etiqueta">Nro. Referencia</div><div class="valor">{{ $esMultiple ? '—' : ($principal->referencia ?? '—') }}</div></div>
    </div>

    <div class="caja-monto">
      <div class="fila-monto">
        <span class="etq">Monto Total Bs.</span>
        <span class="num" style="color:{{ $comprobante->tipo === 'ingreso' ? 'var(--gc-verde)' : 'var(--gc-rojo)' }};">Bs. {{ formatoMoneda($comprobante->monto) }}</span>
      </div>
      <div class="letras">{{ montoALetras($comprobante->monto) }}</div>
    </div>

    <div class="caja-concepto">
      <div class="etq">Descripción / Concepto</div>
      <div>{{ $comprobante->concepto }}</div>
    </div>

    @if($comprobante->nota)
      <div class="caja-concepto">
        <div class="etq">Nota / Observación</div>
        <div>{{ $comprobante->nota }}</div>
      </div>
    @endif

    @if($esMultiple)
      <div class="desglose-formal">
        <table>
          <thead><tr><th>Forma de pago</th><th>Banco/Cta.</th><th>Referencia</th><th>Nota</th><th style="text-align:right;">Monto</th></tr></thead>
          <tbody>
            @foreach($comprobante->pagos as $p)
              <tr><td>{{ ucfirst($p->forma_pago) }}</td><td>{{ collect([$p->banco, $p->cuenta])->filter()->join(' / ') ?: '—' }}</td><td>{{ $p->referencia ?: '—' }}</td><td>{{ $p->nota ?: '—' }}</td><td style="text-align:right;">Bs {{ formatoMoneda($p->monto) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <div class="firmas">
      <div><div style="height:30px;"></div><div class="linea">Elaborado por</div></div>
      <div><div style="height:30px;"></div><div class="linea">Aprobado por</div></div>
    </div>
  </div>
</div>
