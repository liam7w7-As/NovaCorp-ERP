<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota {{ $nota->numero }} - GISECA SRL</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body{background:#f4f6f8;color:#1f2937}.nota-shell{max-width:920px;margin:22px auto;padding:0 14px}.nota-actions{display:flex;justify-content:space-between;gap:8px;margin-bottom:12px}.nota-hoja{background:#fff;border:1px solid #d9dee7;border-radius:8px;padding:28px;box-shadow:0 8px 24px rgba(15,23,42,.08)}.nota-head{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #111827;padding-bottom:16px;margin-bottom:18px}.nota-brand h1{font-size:22px;margin:0}.nota-brand div,.nota-meta div{font-size:12px;color:#4b5563}.nota-numero{text-align:right}.nota-numero h2{font-size:20px;margin:0 0 8px}.nota-datos{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;font-size:13px}.nota-dato{border:1px solid #e5e7eb;border-radius:6px;padding:10px}.nota-dato span{display:block;font-size:10px;text-transform:uppercase;color:#6b7280;font-weight:700;margin-bottom:4px}.nota-tabla{width:100%;border-collapse:collapse;font-size:12.5px}.nota-tabla th{background:#f3f4f6;border:1px solid #d1d5db;padding:8px;text-align:left}.nota-tabla td{border:1px solid #e5e7eb;padding:8px}.nota-tabla .num{text-align:right}.nota-footer{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:52px}.nota-firma{border-top:1px solid #111827;text-align:center;padding-top:8px;font-size:12px}.nota-anulada{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;padding:10px;margin-bottom:14px;font-weight:700;text-align:center}@media(max-width:720px){.nota-head,.nota-datos,.nota-footer{grid-template-columns:1fr;display:grid}.nota-numero{text-align:left}.nota-hoja{padding:18px}.nota-actions{flex-wrap:wrap}}@media print{body{background:#fff}.nota-shell{margin:0;max-width:none}.nota-actions{display:none}.nota-hoja{box-shadow:none;border:0;border-radius:0}.nota-anulada{border:2px solid #111;color:#111;background:#fff}}
    </style>
</head>

<body>
    <div class="nota-shell">
        <div class="nota-actions">
            <a href="{{ route('almacen.index') }}" class="btn-giseca btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Almacén</a>
            <button class="btn-giseca btn-primario btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        </div>

        <article class="nota-hoja">
            @if ($nota->estado === 'anulada')
                <div class="nota-anulada">NOTA ANULADA</div>
            @endif

            <header class="nota-head">
                <div class="nota-brand">
                    <h1>GISECA SRL</h1>
                    <div>{{ $nota->sucursal?->nombre ?? 'Sucursal no registrada' }}</div>
                    <div>Entrega de productos de almacén</div>
                </div>
                <div class="nota-numero">
                    <h2>Nota de Entrega</h2>
                    <div class="codigo-chip">{{ $nota->numero }}</div>
                    <div class="nota-meta">
                        <div>Fecha: {{ $nota->fecha->format('Y-m-d') }}</div>
                        <div>Venta: {{ $nota->venta?->numero ?? '—' }}</div>
                    </div>
                </div>
            </header>

            <section class="nota-datos">
                <div class="nota-dato">
                    <span>Cliente</span>
                    {{ $nota->cliente_nombre }}
                </div>
                <div class="nota-dato">
                    <span>Preparado por</span>
                    {{ $nota->usuario?->name ?? '—' }}
                </div>
            </section>

            <table class="nota-tabla">
                <thead>
                    <tr>
                        <th style="width:150px;">Código</th>
                        <th>Descripción</th>
                        <th style="width:120px;" class="num">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nota->detalles as $detalle)
                        <tr>
                            <td>{{ $detalle->codigo_producto }}</td>
                            <td>{{ $detalle->descripcion_producto }}</td>
                            <td class="num">{{ formatoMoneda($detalle->cantidad) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($nota->observaciones)
                <div class="nota-dato" style="margin-top:16px;">
                    <span>Observaciones</span>
                    {{ $nota->observaciones }}
                </div>
            @endif

            <footer class="nota-footer">
                <div class="nota-firma">Entregado por</div>
                <div class="nota-firma">Recibido por</div>
            </footer>
        </article>
    </div>
</body>

</html>
