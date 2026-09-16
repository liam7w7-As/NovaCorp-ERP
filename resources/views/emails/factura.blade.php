<p>Estimado(a) {{ $factura->venta->cliente_nombre ?? 'cliente' }},</p>

@if($motivo === 'anulada')
<p>Le informamos que la factura electrónica <strong>{{ $factura->numero_factura }}</strong> ha sido <strong>anulada</strong> ante el SIN.</p>
@else
<p>Adjuntamos su factura electrónica <strong>{{ $factura->numero_factura }}</strong> por un total de <strong>Bs {{ formatoMoneda($factura->venta->total ?? 0) }}</strong>.</p>
<p>CUF: {{ $factura->cuf }}</p>
@endif

<p>Atentamente,<br>GISECA SRL</p>
